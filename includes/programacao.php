<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function programacaoEventos(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        'SELECT e.id, e.titulo, e.data_inicio, e.data_fim, e.capacidade
         FROM eventos e
         WHERE EXISTS (
             SELECT 1 FROM evento_organizadores eo
             WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
         )
         ORDER BY e.data_inicio DESC, e.id DESC'
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    return $consulta->fetchAll();
}

function programacaoId(mixed $value, bool $optional = false): ?int
{
    if ($optional && ($value === null || $value === '')) {
        return null;
    }
    if (!is_string($value) && !is_int($value)) {
        throw new DomainException('Um identificador informado é inválido.');
    }
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new DomainException('Um identificador informado é inválido.');
    }

    return $id;
}

function programacaoTexto(mixed $value, string $campo): string
{
    if (!is_string($value)) {
        throw new DomainException("Informe um valor válido para {$campo}.");
    }

    return trim($value);
}

function programacaoData(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$date
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        return null;
    }

    return $date;
}

function programacaoTransacao(PDO $pdo, int $eventoId, int $usuarioId, callable $acao): mixed
{
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT e.id, e.data_inicio, e.data_fim, e.capacidade
             FROM eventos e
             WHERE e.id = :evento_id
               AND EXISTS (
                   SELECT 1 FROM evento_organizadores eo
                   WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
               )
             FOR UPDATE'
        );
        $consulta->execute(['evento_id' => $eventoId, 'usuario_id' => $usuarioId]);
        $evento = $consulta->fetch();
        if (!$evento) {
            throw new DomainException('Evento não encontrado ou você não tem permissão para alterá-lo.');
        }

        $resultado = $acao($evento);
        $pdo->commit();

        return $resultado;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function validarLimitesAtividadesEvento(
    PDO $pdo,
    int $eventoId,
    string $inicio,
    string $fim,
    int $capacidade
): void {
    $consulta = $pdo->prepare(
        'SELECT COUNT(*) FROM atividades
         WHERE evento_id = :evento_id
           AND (inicio < :inicio OR fim > :fim)'
    );
    $consulta->execute(['evento_id' => $eventoId, 'inicio' => $inicio, 'fim' => $fim]);
    if ((int) $consulta->fetchColumn() > 0) {
        throw new DomainException('O período do evento não pode excluir atividades já agendadas.');
    }

    $consulta = $pdo->prepare(
        'SELECT MAX(capacidade) FROM atividades WHERE evento_id = :evento_id'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $maiorCapacidadeAgendada = $consulta->fetchColumn();
    if ($maiorCapacidadeAgendada !== null && $capacidade < (int) $maiorCapacidadeAgendada) {
        throw new DomainException('A capacidade do evento não pode ser menor que a de uma atividade agendada.');
    }
}

function salvarPalestrante(
    PDO $pdo,
    int $eventoId,
    int $usuarioId,
    ?int $palestranteId,
    string $nome,
    string $email,
    string $bio
): void {
    $nome = trim($nome);
    $email = trim($email);
    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 150 || strlen($bio) > 65535) {
        throw new DomainException('Informe o nome do palestrante e respeite os limites dos campos.');
    }
    if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        throw new DomainException('Informe um e-mail válido para o palestrante.');
    }

    programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $palestranteId,
        $nome,
        $email,
        $bio
    ): void {
        if ($palestranteId !== null) {
            $consulta = $pdo->prepare('SELECT id FROM palestrantes WHERE id = :id AND evento_id = :evento_id FOR UPDATE');
            $consulta->execute(['id' => $palestranteId, 'evento_id' => $eventoId]);
            if ($consulta->fetchColumn() === false) {
                throw new DomainException('Palestrante não encontrado neste evento.');
            }
            $consulta = $pdo->prepare(
                'UPDATE palestrantes SET nome = :nome, email = :email, bio = :bio
                 WHERE id = :id AND evento_id = :evento_id'
            );
            $consulta->execute([
                'nome' => $nome,
                'email' => $email !== '' ? $email : null,
                'bio' => $bio !== '' ? $bio : null,
                'id' => $palestranteId,
                'evento_id' => $eventoId,
            ]);
            return;
        }

        $consulta = $pdo->prepare(
            'INSERT INTO palestrantes (evento_id, nome, email, bio)
             VALUES (:evento_id, :nome, :email, :bio)'
        );
        $consulta->execute([
            'evento_id' => $eventoId,
            'nome' => $nome,
            'email' => $email !== '' ? $email : null,
            'bio' => $bio !== '' ? $bio : null,
        ]);
    });
}

function excluirPalestrante(PDO $pdo, int $eventoId, int $usuarioId, int $palestranteId): void
{
    programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $palestranteId
    ): void {
        $consulta = $pdo->prepare(
            'SELECT id FROM palestrantes WHERE id = :id AND evento_id = :evento_id FOR UPDATE'
        );
        $consulta->execute(['id' => $palestranteId, 'evento_id' => $eventoId]);
        if ($consulta->fetchColumn() === false) {
            throw new DomainException('Palestrante não encontrado neste evento.');
        }
        $consulta = $pdo->prepare(
            'SELECT a.titulo
             FROM atividade_palestrantes ap
             JOIN atividades a ON a.id = ap.atividade_id
             WHERE ap.palestrante_id = :palestrante_id
               AND (SELECT COUNT(*) FROM atividade_palestrantes outro WHERE outro.atividade_id = ap.atividade_id) = 1
             LIMIT 1'
        );
        $consulta->execute(['palestrante_id' => $palestranteId]);
        $atividadeUnica = $consulta->fetchColumn();
        if ($atividadeUnica !== false) {
            throw new DomainException(
                'Este é o único palestrante da atividade "' . $atividadeUnica
                . '". Inclua outro palestrante na atividade antes de excluí-lo.'
            );
        }
        $consulta = $pdo->prepare(
            'DELETE FROM palestrantes WHERE id = :id AND evento_id = :evento_id'
        );
        $consulta->execute(['id' => $palestranteId, 'evento_id' => $eventoId]);
    });
}

function salvarEspaco(
    PDO $pdo,
    int $eventoId,
    int $usuarioId,
    ?int $espacoId,
    string $nome,
    int $capacidade,
    string $recursos
): void {
    $nome = trim($nome);
    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 120 || strlen($recursos) > 500 || $capacidade < 1) {
        throw new DomainException('Informe nome e capacidade positiva para o espaço e respeite os limites dos campos.');
    }

    programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $espacoId,
        $nome,
        $capacidade,
        $recursos
    ): void {
        if ($espacoId !== null) {
            $consulta = $pdo->prepare('SELECT id FROM espacos WHERE id = :id AND evento_id = :evento_id FOR UPDATE');
            $consulta->execute(['id' => $espacoId, 'evento_id' => $eventoId]);
            if ($consulta->fetchColumn() === false) {
                throw new DomainException('Espaço não encontrado neste evento.');
            }
            $consulta = $pdo->prepare(
                'SELECT MAX(capacidade) FROM atividades WHERE espaco_id = :espaco_id AND evento_id = :evento_id'
            );
            $consulta->execute(['espaco_id' => $espacoId, 'evento_id' => $eventoId]);
            $maiorCapacidadeAgendada = $consulta->fetchColumn();
            if ($maiorCapacidadeAgendada !== null && $capacidade < (int) $maiorCapacidadeAgendada) {
                throw new DomainException('A capacidade não pode ser menor que a de uma atividade agendada neste espaço.');
            }
            $consulta = $pdo->prepare(
                'UPDATE espacos SET nome = :nome, capacidade = :capacidade, recursos = :recursos
                 WHERE id = :id AND evento_id = :evento_id'
            );
            $consulta->execute([
                'nome' => $nome,
                'capacidade' => $capacidade,
                'recursos' => $recursos !== '' ? $recursos : null,
                'id' => $espacoId,
                'evento_id' => $eventoId,
            ]);
            return;
        }

        $consulta = $pdo->prepare(
            'INSERT INTO espacos (evento_id, nome, capacidade, recursos)
             VALUES (:evento_id, :nome, :capacidade, :recursos)'
        );
        $consulta->execute([
            'evento_id' => $eventoId,
            'nome' => $nome,
            'capacidade' => $capacidade,
            'recursos' => $recursos !== '' ? $recursos : null,
        ]);
    });
}

function excluirEspaco(PDO $pdo, int $eventoId, int $usuarioId, int $espacoId): void
{
    programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $espacoId
    ): void {
        $consulta = $pdo->prepare('SELECT id FROM espacos WHERE id = :id AND evento_id = :evento_id FOR UPDATE');
        $consulta->execute(['id' => $espacoId, 'evento_id' => $eventoId]);
        if ($consulta->fetchColumn() === false) {
            throw new DomainException('Espaço não encontrado neste evento.');
        }
        $consulta = $pdo->prepare(
            'SELECT COUNT(*) FROM atividades WHERE espaco_id = :espaco_id AND evento_id = :evento_id'
        );
        $consulta->execute(['espaco_id' => $espacoId, 'evento_id' => $eventoId]);
        if ((int) $consulta->fetchColumn() > 0) {
            throw new DomainException('Remova ou altere as atividades agendadas antes de excluir este espaço.');
        }
        $consulta = $pdo->prepare('DELETE FROM espacos WHERE id = :id AND evento_id = :evento_id');
        $consulta->execute(['id' => $espacoId, 'evento_id' => $eventoId]);
    });
}

function salvarAtividade(
    PDO $pdo,
    int $eventoId,
    int $usuarioId,
    ?int $atividadeId,
    string $titulo,
    string $descricao,
    string $tipo,
    string $inicioTexto,
    string $fimTexto,
    int $espacoId,
    int $capacidade,
    array $palestrantesIds
): void {
    $titulo = trim($titulo);
    $tipo = trim($tipo);
    $inicio = programacaoData($inicioTexto);
    $fim = programacaoData($fimTexto);
    $palestrantesIds = array_values(array_unique($palestrantesIds));
    if (
        $titulo === '' || mb_strlen($titulo, 'UTF-8') > 180
        || $tipo === '' || mb_strlen($tipo, 'UTF-8') > 80
        || strlen($descricao) > 65535
        || $inicio === null || $fim === null || $fim <= $inicio
        || $espacoId < 1 || $capacidade < 1
    ) {
        throw new DomainException('Verifique título, tipo, horários, espaço e capacidade da atividade.');
    }

    programacaoTransacao($pdo, $eventoId, $usuarioId, static function (array $evento) use (
        $pdo,
        $eventoId,
        $atividadeId,
        $titulo,
        $descricao,
        $tipo,
        $inicio,
        $fim,
        $espacoId,
        $capacidade,
        $palestrantesIds
    ): void {
        $eventoInicio = new DateTimeImmutable((string) $evento['data_inicio']);
        $eventoFim = new DateTimeImmutable((string) $evento['data_fim']);
        if ($inicio < $eventoInicio || $fim > $eventoFim) {
            throw new DomainException('A atividade deve acontecer dentro do período do evento.');
        }
        if ($evento['capacidade'] !== null && $capacidade > (int) $evento['capacidade']) {
            throw new DomainException('A capacidade da atividade não pode superar a capacidade do evento.');
        }

        if ($atividadeId !== null) {
            $consulta = $pdo->prepare(
                'SELECT id FROM atividades WHERE id = :id AND evento_id = :evento_id FOR UPDATE'
            );
            $consulta->execute(['id' => $atividadeId, 'evento_id' => $eventoId]);
            if ($consulta->fetchColumn() === false) {
                throw new DomainException('Atividade não encontrada neste evento.');
            }
        }

        $consulta = $pdo->prepare(
            'SELECT capacidade FROM espacos WHERE id = :espaco_id AND evento_id = :evento_id FOR UPDATE'
        );
        $consulta->execute(['espaco_id' => $espacoId, 'evento_id' => $eventoId]);
        $capacidadeEspaco = $consulta->fetchColumn();
        if ($capacidadeEspaco === false) {
            throw new DomainException('Selecione um espaço cadastrado para este evento.');
        }
        if ($capacidade > (int) $capacidadeEspaco) {
            throw new DomainException('A capacidade da atividade não pode superar a capacidade do espaço.');
        }

        if ($palestrantesIds !== []) {
            $placeholders = implode(',', array_fill(0, count($palestrantesIds), '?'));
            $consulta = $pdo->prepare(
                "SELECT id FROM palestrantes WHERE evento_id = ? AND id IN ({$placeholders}) FOR UPDATE"
            );
            $consulta->execute(array_merge([$eventoId], $palestrantesIds));
            if (count($consulta->fetchAll(PDO::FETCH_COLUMN)) !== count($palestrantesIds)) {
                throw new DomainException('Selecione somente palestrantes cadastrados neste evento.');
            }
        }
        if ($palestrantesIds === []) {
            throw new DomainException('Selecione ao menos um palestrante para a atividade.');
        }

        $parametrosConflito = [
            'evento_id' => $eventoId,
            'inicio' => $fim->format('Y-m-d H:i:s'),
            'fim' => $inicio->format('Y-m-d H:i:s'),
        ];
        $sqlConflito = 'SELECT a.id FROM atividades a
                        WHERE a.evento_id = :evento_id
                          AND a.inicio < :inicio AND a.fim > :fim';
        if ($atividadeId !== null) {
            $sqlConflito .= ' AND a.id <> :atividade_id';
            $parametrosConflito['atividade_id'] = $atividadeId;
        }
        $consulta = $pdo->prepare(
            $sqlConflito . ' AND a.espaco_id = :espaco_id LIMIT 1 FOR UPDATE'
        );
        $consulta->execute($parametrosConflito + ['espaco_id' => $espacoId]);
        if ($consulta->fetchColumn() !== false) {
            throw new DomainException('Este espaço já está reservado em parte ou em todo esse horário.');
        }

        if ($palestrantesIds !== []) {
            $marcadores = implode(',', array_fill(0, count($palestrantesIds), '?'));
            $sqlConflitoPalestrantes = 'SELECT ap.palestrante_id
                                        FROM atividade_palestrantes ap
                                        JOIN atividades a ON a.id = ap.atividade_id AND a.evento_id = ap.evento_id
                                        WHERE ap.evento_id = ?
                                          AND a.inicio < ? AND a.fim > ?
                                          AND ap.palestrante_id IN (' . $marcadores . ')';
            $valoresConflito = array_merge(
                [$eventoId, $fim->format('Y-m-d H:i:s'), $inicio->format('Y-m-d H:i:s')],
                $palestrantesIds
            );
            if ($atividadeId !== null) {
                $sqlConflitoPalestrantes .= ' AND a.id <> ?';
                $valoresConflito[] = $atividadeId;
            }
            $consulta = $pdo->prepare($sqlConflitoPalestrantes . ' LIMIT 1 FOR UPDATE');
            $consulta->execute($valoresConflito);
            if ($consulta->fetchColumn() !== false) {
                throw new DomainException('Um ou mais palestrantes já estão em outra atividade nesse horário.');
            }
        }

        $parametrosAtividade = [
            'evento_id' => $eventoId,
            'espaco_id' => $espacoId,
            'titulo' => $titulo,
            'descricao' => $descricao !== '' ? $descricao : null,
            'tipo' => $tipo,
            'inicio' => $inicio->format('Y-m-d H:i:s'),
            'fim' => $fim->format('Y-m-d H:i:s'),
            'capacidade' => $capacidade,
        ];
        if ($atividadeId === null) {
            $consulta = $pdo->prepare(
                'INSERT INTO atividades
                    (evento_id, espaco_id, titulo, descricao, tipo, inicio, fim, capacidade)
                 VALUES
                    (:evento_id, :espaco_id, :titulo, :descricao, :tipo, :inicio, :fim, :capacidade)'
            );
            $consulta->execute($parametrosAtividade);
            $atividadeId = (int) $pdo->lastInsertId();
        } else {
            $consulta = $pdo->prepare(
                'UPDATE atividades SET espaco_id = :espaco_id, titulo = :titulo,
                    descricao = :descricao, tipo = :tipo, inicio = :inicio,
                    fim = :fim, capacidade = :capacidade
                 WHERE id = :id AND evento_id = :evento_id'
            );
            $consulta->execute($parametrosAtividade + ['id' => $atividadeId]);
        }

        $consulta = $pdo->prepare('DELETE FROM atividade_palestrantes WHERE atividade_id = :atividade_id');
        $consulta->execute(['atividade_id' => $atividadeId]);
        $consulta = $pdo->prepare(
            'INSERT INTO atividade_palestrantes (atividade_id, palestrante_id, evento_id)
             VALUES (:atividade_id, :palestrante_id, :evento_id)'
        );
        foreach ($palestrantesIds as $palestranteId) {
            $consulta->execute([
                'atividade_id' => $atividadeId,
                'palestrante_id' => $palestranteId,
                'evento_id' => $eventoId,
            ]);
        }
    });
}

function excluirAtividade(PDO $pdo, int $eventoId, int $usuarioId, int $atividadeId): void
{
    programacaoTransacao($pdo, $eventoId, $usuarioId, static function () use (
        $pdo,
        $eventoId,
        $atividadeId
    ): void {
        $consulta = $pdo->prepare('SELECT COUNT(*) FROM certificados WHERE atividade_id = :id');
        $consulta->execute(['id' => $atividadeId]);
        if ((int) $consulta->fetchColumn() > 0) {
            throw new DomainException('Esta atividade possui certificados emitidos e não pode ser excluída.');
        }
        $consulta = $pdo->prepare('DELETE FROM atividades WHERE id = :id AND evento_id = :evento_id');
        $consulta->execute(['id' => $atividadeId, 'evento_id' => $eventoId]);
        if ($consulta->rowCount() === 0) {
            throw new DomainException('Atividade não encontrada neste evento.');
        }
    });
}
