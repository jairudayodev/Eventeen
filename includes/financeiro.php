<?php
declare(strict_types=1);
require_once __DIR__ . '/programacao.php';

function financeiroCentavos(mixed $valor, bool $permitirZero = false): int
{
    if (!is_string($valor) && !is_int($valor)) {
        throw new DomainException('Informe um valor monetário válido.');
    }
    $valor = trim((string) $valor);
    if (str_contains($valor, ',')) {
        if (!preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$|^\d+(?:,\d{1,2})?$/', $valor)) {
            throw new DomainException('Informe o valor no formato 1234,56.');
        }
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $valor)) {
        throw new DomainException('Informe o valor no formato 1234,56.');
    }

    [$reais, $centavos] = array_pad(explode('.', $valor, 2), 2, '0');
    $reais = ltrim($reais, '0');
    $reais = $reais === '' ? '0' : $reais;
    $centavos = str_pad($centavos, 2, '0');
    if (strlen($reais) > 10 || (strlen($reais) === 10 && $reais > '9999999999')) {
        throw new DomainException('O valor não pode exceder R$ 9.999.999.999,99.');
    }

    $total = ((int) $reais * 100) + (int) $centavos;
    if ($total < 1 && !($permitirZero && $total === 0)) {
        throw new DomainException('O valor deve ser maior que zero.');
    }

    return $total;
}

function financeiroDecimal(int $centavos): string
{
    return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
}

function financeiroMoeda(string|int|float|null $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function financeiroMoedaCentavos(int $centavos): string
{
    $sinal = $centavos < 0 ? '-' : '';
    return $sinal . 'R$ ' . number_format(abs($centavos) / 100, 2, ',', '.');
}

function salvarParceiro(PDO $pdo, int $usuarioId, string $nome, string $email, string $telefone): int
{
    $nome = trim($nome);
    $email = trim($email);
    $telefone = trim($telefone);
    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 150) {
        throw new DomainException('Informe o nome do parceiro, com até 150 caracteres.');
    }
    if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        throw new DomainException('Informe um e-mail válido para o parceiro.');
    }
    if (strlen($telefone) > 40) {
        throw new DomainException('O telefone do parceiro deve ter até 40 caracteres.');
    }
    if ($email !== '') {
        $consulta = $pdo->prepare(
            'SELECT id FROM parceiros WHERE criado_por = :usuario_id AND email = :email'
        );
        $consulta->execute(['usuario_id' => $usuarioId, 'email' => $email]);
        if ($consulta->fetchColumn() !== false) {
            throw new DomainException('Já existe um parceiro cadastrado com este e-mail.');
        }
    }

    $consulta = $pdo->prepare(
        'INSERT INTO parceiros (criado_por, nome, email, telefone)
         VALUES (:usuario_id, :nome, :email, :telefone)'
    );
    $consulta->execute([
        'usuario_id' => $usuarioId,
        'nome' => $nome,
        'email' => $email !== '' ? $email : null,
        'telefone' => $telefone !== '' ? $telefone : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function salvarPatrocinio(
    PDO $pdo,
    int $eventoId,
    int $usuarioId,
    int $parceiroId,
    string $cota,
    string $contrapartida,
    int $valorCentavos
): int {
    $cota = trim($cota);
    $contrapartida = trim($contrapartida);
    if ($cota === '' || mb_strlen($cota, 'UTF-8') > 80 || mb_strlen($contrapartida, 'UTF-8') > 500) {
        throw new DomainException('Informe a cota e respeite os limites dos campos.');
    }
    if ($valorCentavos < 1) {
        throw new DomainException('O valor do patrocínio deve ser maior que zero.');
    }

    return programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $usuarioId,
        $parceiroId,
        $cota,
        $contrapartida,
        $valorCentavos
    ): int {
        $consulta = $pdo->prepare(
            'SELECT id FROM parceiros WHERE id = :parceiro_id AND criado_por = :usuario_id FOR UPDATE'
        );
        $consulta->execute(['parceiro_id' => $parceiroId, 'usuario_id' => $usuarioId]);
        if ($consulta->fetchColumn() === false) {
            throw new DomainException('Parceiro não encontrado na sua lista.');
        }
        $consulta = $pdo->prepare(
            'INSERT INTO patrocinios (evento_id, parceiro_id, cota, contrapartida, valor)
             VALUES (:evento_id, :parceiro_id, :cota, :contrapartida, :valor)'
        );
        $consulta->execute([
            'evento_id' => $eventoId,
            'parceiro_id' => $parceiroId,
            'cota' => $cota,
            'contrapartida' => $contrapartida !== '' ? $contrapartida : null,
            'valor' => financeiroDecimal($valorCentavos),
        ]);

        return (int) $pdo->lastInsertId();
    });
}

function atualizarStatusPatrocinio(PDO $pdo, int $patrocinioId, int $usuarioId, string $status): void
{
    if (!in_array($status, ['negociacao', 'confirmado', 'cancelado'], true)) {
        throw new DomainException('O status do patrocínio é inválido.');
    }
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT p.id, p.status
             FROM patrocinios p
             JOIN evento_organizadores eo ON eo.evento_id = p.evento_id
             WHERE p.id = :patrocinio_id AND eo.usuario_id = :usuario_id
             FOR UPDATE'
        );
        $consulta->execute(['patrocinio_id' => $patrocinioId, 'usuario_id' => $usuarioId]);
        $patrocinio = $consulta->fetch();
        if (!$patrocinio) {
            throw new DomainException('Patrocínio não encontrado ou você não tem permissão para alterá-lo.');
        }
        if ($patrocinio['status'] === 'cancelado' && $status !== 'cancelado') {
            throw new DomainException('Um patrocínio cancelado não pode ser reativado.');
        }
        if ($status === 'cancelado') {
            $consulta = $pdo->prepare(
                "SELECT COUNT(*) FROM financeiro_lancamentos
                 WHERE patrocinio_id = :patrocinio_id AND status <> 'cancelado'"
            );
            $consulta->execute(['patrocinio_id' => $patrocinioId]);
            if ((int) $consulta->fetchColumn() > 0) {
                throw new DomainException('Cancele os pagamentos vinculados antes de cancelar o patrocínio.');
            }
        }
        $consulta = $pdo->prepare('UPDATE patrocinios SET status = :status WHERE id = :patrocinio_id');
        $consulta->execute(['status' => $status, 'patrocinio_id' => $patrocinioId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function salvarLancamentoFinanceiro(
    PDO $pdo,
    int $usuarioId,
    string $descricao,
    string $tipo,
    int $valorCentavos,
    string $data,
    string $status,
    ?int $eventoId,
    array $rateios,
    ?int $patrocinioId
): int {
    $descricao = trim($descricao);
    if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 180) {
        throw new DomainException('Informe uma descrição com até 180 caracteres.');
    }
    if (!in_array($tipo, ['entrada', 'despesa'], true)) {
        throw new DomainException('Selecione entrada ou despesa.');
    }
    if (!in_array($status, ['pendente', 'realizado'], true)) {
        throw new DomainException('Selecione um status válido para o lançamento.');
    }
    if ($valorCentavos < 1) {
        throw new DomainException('O valor do lançamento deve ser maior que zero.');
    }
    $dataValida = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    $errosData = DateTimeImmutable::getLastErrors();
    if (
        !$dataValida
        || ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0))
        || $dataValida->format('Y-m-d') !== $data
    ) {
        throw new DomainException('Informe uma data válida para o lançamento.');
    }
    if ($tipo === 'entrada') {
        if ($eventoId === null || $rateios !== []) {
            throw new DomainException('Uma entrada deve ser vinculada a um único evento.');
        }
        $rateios = [$eventoId => financeiroDecimal($valorCentavos)];
    } elseif ($patrocinioId !== null) {
        throw new DomainException('Somente entradas podem ser vinculadas a um patrocínio.');
    } elseif ($rateios === [] && $eventoId !== null) {
        $rateios = [$eventoId => financeiroDecimal($valorCentavos)];
    }
    if ($rateios === []) {
        throw new DomainException('Associe a despesa a um evento ou informe os valores rateados.');
    }

    $rateiosValidados = [];
    $somaCentavos = 0;
    foreach ($rateios as $idRecebido => $valorRecebido) {
        $id = programacaoId((string) $idRecebido);
        $centavos = financeiroCentavos($valorRecebido);
        $rateiosValidados[$id] = $centavos;
        $somaCentavos += $centavos;
    }
    if ($somaCentavos !== $valorCentavos) {
        throw new DomainException('A soma dos rateios deve ser igual ao valor total do lançamento.');
    }
    if ($patrocinioId !== null && (count($rateiosValidados) !== 1 || !isset($rateiosValidados[$eventoId]))) {
        throw new DomainException('O pagamento do patrocínio deve pertencer ao evento do contrato.');
    }

    $pdo->beginTransaction();
    try {
        $idsEventos = array_keys($rateiosValidados);
        sort($idsEventos);
        $placeholders = [];
        $parametros = ['usuario_id' => $usuarioId];
        foreach ($idsEventos as $indice => $id) {
            $chave = 'evento_' . $indice;
            $placeholders[] = ':' . $chave;
            $parametros[$chave] = $id;
        }
        $consulta = $pdo->prepare(
            'SELECT e.id FROM eventos e
             WHERE e.id IN (' . implode(', ', $placeholders) . ')
               AND EXISTS (
                   SELECT 1 FROM evento_organizadores eo
                   WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
               )
             ORDER BY e.id FOR UPDATE'
        );
        $consulta->execute($parametros);
        $eventosPermitidos = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        if ($eventosPermitidos !== $idsEventos) {
            throw new DomainException('Um ou mais eventos do rateio não existem ou não estão sob sua organização.');
        }

        if ($patrocinioId !== null) {
            $consulta = $pdo->prepare(
                "SELECT p.evento_id, p.valor
                 FROM patrocinios p
                 JOIN evento_organizadores eo ON eo.evento_id = p.evento_id
                 WHERE p.id = :patrocinio_id
                   AND eo.usuario_id = :usuario_id
                   AND p.status = 'confirmado'
                 FOR UPDATE"
            );
            $consulta->execute(['patrocinio_id' => $patrocinioId, 'usuario_id' => $usuarioId]);
            $patrocinio = $consulta->fetch();
            if (!$patrocinio || (int) $patrocinio['evento_id'] !== $eventoId) {
                throw new DomainException('O patrocínio deve estar confirmado e pertencer ao evento selecionado.');
            }
            $consulta = $pdo->prepare(
                "SELECT COALESCE(SUM(valor), 0)
                 FROM financeiro_lancamentos
                 WHERE patrocinio_id = :patrocinio_id AND status <> 'cancelado'"
            );
            $consulta->execute(['patrocinio_id' => $patrocinioId]);
            $valorComprometido = financeiroCentavos((string) $consulta->fetchColumn(), true);
            if ($valorComprometido + $valorCentavos > financeiroCentavos((string) $patrocinio['valor'])) {
                throw new DomainException('Os pagamentos ultrapassam o valor do patrocínio.');
            }
        }

        $consulta = $pdo->prepare(
            'INSERT INTO financeiro_lancamentos
                (criado_por, patrocinio_id, descricao, tipo, valor, data_movimentacao, status)
             VALUES (:usuario_id, :patrocinio_id, :descricao, :tipo, :valor, :data, :status)'
        );
        $consulta->execute([
            'usuario_id' => $usuarioId,
            'patrocinio_id' => $patrocinioId,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'valor' => financeiroDecimal($valorCentavos),
            'data' => $data,
            'status' => $status,
        ]);
        $lancamentoId = (int) $pdo->lastInsertId();
        $consulta = $pdo->prepare(
            'INSERT INTO financeiro_rateios (lancamento_id, evento_id, valor)
             VALUES (:lancamento_id, :evento_id, :valor)'
        );
        foreach ($rateiosValidados as $id => $centavos) {
            $consulta->execute([
                'lancamento_id' => $lancamentoId,
                'evento_id' => $id,
                'valor' => financeiroDecimal($centavos),
            ]);
        }
        $pdo->commit();

        return $lancamentoId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function atualizarStatusLancamento(PDO $pdo, int $lancamentoId, int $usuarioId, string $status): void
{
    if (!in_array($status, ['realizado', 'cancelado'], true)) {
        throw new DomainException('A ação do lançamento é inválida.');
    }
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT l.id, l.status
             FROM financeiro_lancamentos l
             WHERE l.id = :lancamento_id AND l.criado_por = :usuario_id
               AND EXISTS (
                   SELECT 1 FROM financeiro_rateios fr
                   JOIN evento_organizadores eo ON eo.evento_id = fr.evento_id
                   WHERE fr.lancamento_id = l.id AND eo.usuario_id = :organizador_id
               )
             FOR UPDATE'
        );
        $consulta->execute([
            'lancamento_id' => $lancamentoId,
            'usuario_id' => $usuarioId,
            'organizador_id' => $usuarioId,
        ]);
        $lancamento = $consulta->fetch();
        if (!$lancamento) {
            throw new DomainException('Lançamento não encontrado ou você não tem permissão para alterá-lo.');
        }
        if ($lancamento['status'] !== 'pendente') {
            throw new DomainException('Somente lançamentos pendentes podem ser concluídos ou cancelados.');
        }
        $consulta = $pdo->prepare(
            'UPDATE financeiro_lancamentos SET status = :status WHERE id = :lancamento_id'
        );
        $consulta->execute(['status' => $status, 'lancamento_id' => $lancamentoId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function resumoFinanceiroEvento(PDO $pdo, int $eventoId): array
{
    $consulta = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN l.tipo = 'entrada' AND l.status = 'realizado' THEN fr.valor ELSE 0 END), 0) AS entradas,
            COALESCE(SUM(CASE WHEN l.tipo = 'despesa' AND l.status = 'realizado' THEN fr.valor ELSE 0 END), 0) AS despesas,
            COALESCE(SUM(CASE WHEN l.status = 'pendente' THEN fr.valor ELSE 0 END), 0) AS pendente,
            COUNT(DISTINCT CASE WHEN l.status = 'pendente' THEN l.id END) AS lancamentos_pendentes
         FROM financeiro_rateios fr
         JOIN financeiro_lancamentos l ON l.id = fr.lancamento_id
         WHERE fr.evento_id = :evento_id AND l.status <> 'cancelado'"
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $resumo = $consulta->fetch();
    $resumo['saldo_centavos'] = financeiroCentavos((string) $resumo['entradas'], true)
        - financeiroCentavos((string) $resumo['despesas'], true);

    return $resumo;
}

function listarLancamentosEvento(PDO $pdo, int $eventoId): array
{
    $consulta = $pdo->prepare(
        'SELECT l.id, l.descricao, l.tipo, l.valor, l.data_movimentacao, l.status,
                fr.valor AS valor_evento, p.nome AS parceiro_nome,
                (SELECT GROUP_CONCAT(e2.titulo ORDER BY e2.titulo SEPARATOR \', \')
                 FROM financeiro_rateios fr2
                 JOIN eventos e2 ON e2.id = fr2.evento_id
                 WHERE fr2.lancamento_id = l.id) AS eventos
         FROM financeiro_rateios fr
         JOIN financeiro_lancamentos l ON l.id = fr.lancamento_id
         LEFT JOIN patrocinios pa ON pa.id = l.patrocinio_id
         LEFT JOIN parceiros p ON p.id = pa.parceiro_id
         WHERE fr.evento_id = :evento_id
         ORDER BY l.data_movimentacao DESC, l.id DESC'
    );
    $consulta->execute(['evento_id' => $eventoId]);

    return $consulta->fetchAll();
}
