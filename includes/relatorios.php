<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

/**
 * Eventos concluídos em que o participante manteve a inscrição Confirmada (RF12).
 */
function historicoEventosParticipante(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT e.id, e.titulo, e.categoria, e.data_inicio, e.data_fim,
                (SELECT COUNT(*) FROM presencas_atividades pa
                 WHERE pa.usuario_id = i.usuario_id AND pa.evento_id = e.id) AS atividades_presentes,
                (SELECT COUNT(*) FROM certificados c
                 WHERE c.usuario_id = i.usuario_id AND c.evento_id = e.id) AS certificados
         FROM inscricoes i
         JOIN eventos e ON e.id = i.evento_id
         WHERE i.usuario_id = :usuario_id
           AND i.status = 'ativa'
           AND e.data_fim < NOW()
         ORDER BY e.data_inicio DESC, e.id DESC"
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    return $consulta->fetchAll();
}

/**
 * Atividades concluídas com presença confirmada e, se houver, o certificado emitido.
 */
function historicoAtividadesParticipante(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT a.id AS atividade_id, a.titulo AS atividade_titulo, a.inicio, a.fim,
                e.id AS evento_id, e.titulo AS evento_titulo, e.categoria,
                s.nome AS espaco_nome, c.codigo_validacao
         FROM presencas_atividades pa
         JOIN atividades a ON a.id = pa.atividade_id AND a.evento_id = pa.evento_id
         JOIN eventos e ON e.id = pa.evento_id
         JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id
         LEFT JOIN espacos s ON s.id = a.espaco_id AND s.evento_id = a.evento_id
         LEFT JOIN certificados c ON c.atividade_id = pa.atividade_id AND c.usuario_id = pa.usuario_id
         WHERE pa.usuario_id = :usuario_id
           AND i.status = 'ativa'
           AND a.fim < NOW()
         ORDER BY a.inicio DESC, a.id DESC"
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    return $consulta->fetchAll();
}

/**
 * Normaliza os filtros do relatório: evento opcional e período (datas no formato AAAA-MM-DD).
 */
function relatorioFiltros(array $entrada): array
{
    $filtros = ['evento_id' => null, 'de' => null, 'ate' => null];
    $evento = $entrada['evento_id'] ?? '';
    if ((is_string($evento) || is_int($evento)) && $evento !== '') {
        $id = filter_var($evento, FILTER_VALIDATE_INT);
        $filtros['evento_id'] = $id !== false && $id > 0 ? $id : null;
    }
    foreach (['de', 'ate'] as $campo) {
        $valor = $entrada[$campo] ?? '';
        if (!is_string($valor) || $valor === '') {
            continue;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($data !== false && $data->format('Y-m-d') === $valor) {
            $filtros[$campo] = $valor;
        }
    }
    if ($filtros['de'] !== null && $filtros['ate'] !== null && $filtros['de'] > $filtros['ate']) {
        [$filtros['de'], $filtros['ate']] = [$filtros['ate'], $filtros['de']];
    }

    return $filtros;
}

function relatorioCondicoesEvento(array $filtros, array &$parametros, string $alias = 'e'): string
{
    $sql = '';
    if ($filtros['evento_id'] !== null) {
        $sql .= " AND {$alias}.id = :filtro_evento";
        $parametros['filtro_evento'] = $filtros['evento_id'];
    }
    if ($filtros['de'] !== null) {
        $sql .= " AND {$alias}.data_inicio >= :filtro_de";
        $parametros['filtro_de'] = $filtros['de'] . ' 00:00:00';
    }
    if ($filtros['ate'] !== null) {
        $sql .= " AND {$alias}.data_inicio <= :filtro_ate";
        $parametros['filtro_ate'] = $filtros['ate'] . ' 23:59:59';
    }

    return $sql;
}

/**
 * Inscrições por evento (RF13): vagas, inscrições Confirmadas e Canceladas e participantes
 * distintos com presença em ao menos uma atividade (evita dupla contagem entre atividades).
 */
function relatorioPorEvento(PDO $pdo, int $organizadorId, array $filtros = []): array
{
    $filtros += ['evento_id' => null, 'de' => null, 'ate' => null];
    $parametros = ['usuario_id' => $organizadorId];
    $sql = "SELECT e.id, e.titulo, e.status, e.data_inicio, e.data_fim, e.capacidade,
                   (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'ativa') AS confirmadas,
                   (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'cancelada') AS canceladas,
                   (SELECT COUNT(DISTINCT pa.usuario_id)
                    FROM presencas_atividades pa
                    JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id AND i.status = 'ativa'
                    WHERE pa.evento_id = e.id) AS presentes,
                   (SELECT COUNT(*) FROM atividades a WHERE a.evento_id = e.id) AS atividades,
                   (SELECT COUNT(*) FROM certificados c WHERE c.evento_id = e.id) AS certificados
            FROM eventos e
            WHERE EXISTS (
                SELECT 1 FROM evento_organizadores eo
                WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
            )" . relatorioCondicoesEvento($filtros, $parametros) . '
            ORDER BY e.data_inicio DESC, e.id DESC';
    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    $linhas = $consulta->fetchAll();
    foreach ($linhas as &$linha) {
        $confirmadas = (int) $linha['confirmadas'];
        $linha['taxa_presenca'] = $confirmadas > 0 ? (int) round((int) $linha['presentes'] * 100 / $confirmadas) : 0;
    }
    unset($linha);

    return $linhas;
}

/**
 * Presenças por atividade (RF13): inscritos Confirmados no evento e presenças na atividade.
 */
function relatorioPorAtividade(PDO $pdo, int $organizadorId, array $filtros = []): array
{
    $filtros += ['evento_id' => null, 'de' => null, 'ate' => null];
    $parametros = ['usuario_id' => $organizadorId];
    $sql = "SELECT a.id, a.evento_id, e.titulo AS evento_titulo, a.titulo AS atividade_titulo,
                   a.tipo, a.inicio, a.fim, a.capacidade,
                   (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = a.evento_id AND i.status = 'ativa') AS inscritos,
                   (SELECT COUNT(*)
                    FROM presencas_atividades pa
                    JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id AND i.status = 'ativa'
                    WHERE pa.atividade_id = a.id) AS presencas
            FROM atividades a
            JOIN eventos e ON e.id = a.evento_id
            WHERE EXISTS (
                SELECT 1 FROM evento_organizadores eo
                WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
            )" . relatorioCondicoesEvento($filtros, $parametros) . '
            ORDER BY a.inicio DESC, a.id DESC';
    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    $linhas = $consulta->fetchAll();
    foreach ($linhas as &$linha) {
        $inscritos = (int) $linha['inscritos'];
        $linha['taxa_presenca'] = $inscritos > 0 ? (int) round((int) $linha['presencas'] * 100 / $inscritos) : 0;
    }
    unset($linha);

    return $linhas;
}

function relatorioResumo(array $eventos): array
{
    $resumo = ['eventos' => count($eventos), 'confirmadas' => 0, 'canceladas' => 0, 'presentes' => 0, 'certificados' => 0];
    foreach ($eventos as $evento) {
        $resumo['confirmadas'] += (int) $evento['confirmadas'];
        $resumo['canceladas'] += (int) $evento['canceladas'];
        $resumo['presentes'] += (int) $evento['presentes'];
        $resumo['certificados'] += (int) $evento['certificados'];
    }
    $resumo['taxa_presenca'] = $resumo['confirmadas'] > 0
        ? (int) round($resumo['presentes'] * 100 / $resumo['confirmadas'])
        : 0;

    return $resumo;
}
