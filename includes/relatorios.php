<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function historicoEventosParticipante(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT e.id, e.titulo, e.categoria, e.data_inicio, e.data_fim,
                i.presenca_confirmada, i.certificado_url
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

function historicoAtividadesParticipante(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT a.id AS atividade_id, a.titulo AS atividade_titulo, a.inicio, a.fim,
                e.id AS evento_id, e.titulo AS evento_titulo, e.categoria,
                s.nome AS espaco_nome
         FROM presencas_atividades pa
         JOIN atividades a ON a.id = pa.atividade_id AND a.evento_id = pa.evento_id
         JOIN eventos e ON e.id = pa.evento_id
         JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id
         LEFT JOIN espacos s ON s.id = a.espaco_id AND s.evento_id = a.evento_id
         WHERE pa.usuario_id = :usuario_id
           AND i.status = 'ativa'
           AND a.fim < NOW()
         ORDER BY a.inicio DESC, a.id DESC"
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    return $consulta->fetchAll();
}

function emitirCertificado(PDO $pdo, int $usuarioId, int $eventoId, string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        throw new DomainException('Informe uma URL válida para o certificado.');
    }

    $consulta = $pdo->prepare(
        "SELECT i.id
         FROM inscricoes i
         JOIN eventos e ON e.id = i.evento_id
         WHERE i.usuario_id = :usuario_id
           AND i.evento_id = :evento_id
           AND i.status = 'ativa'
           AND i.presenca_confirmada = 1
           AND e.data_fim < NOW()
           AND (i.certificado_url IS NULL OR i.certificado_url = '')
         FOR UPDATE"
    );
    $consulta->execute(['usuario_id' => $usuarioId, 'evento_id' => $eventoId]);
    if ($consulta->fetchColumn() === false) {
        return false;
    }

    $consulta = $pdo->prepare(
        "UPDATE inscricoes
         SET certificado_url = :certificado_url
         WHERE usuario_id = :usuario_id
           AND evento_id = :evento_id
           AND status = 'ativa'
           AND presenca_confirmada = 1
           AND (certificado_url IS NULL OR certificado_url = '')"
    );
    $consulta->execute([
        'usuario_id' => $usuarioId,
        'evento_id' => $eventoId,
        'certificado_url' => $url,
    ]);

    return $consulta->rowCount() === 1;
}

function relatorioResumoOrganizador(PDO $pdo, int $organizadorId): array
{
    $consulta = $pdo->prepare(
        "SELECT COUNT(DISTINCT i.id) AS total_inscricoes,
                COUNT(DISTINCT CASE WHEN i.presenca_confirmada = 1 THEN i.id END) AS total_presencas,
                COUNT(DISTINCT CASE WHEN e.data_fim < NOW() THEN e.id END) AS eventos_concluidos,
                COUNT(DISTINCT CASE WHEN i.certificado_url IS NOT NULL AND i.certificado_url <> '' THEN i.id END) AS certificados_emitidos
         FROM evento_organizadores eo
         LEFT JOIN eventos e ON e.id = eo.evento_id
         LEFT JOIN inscricoes i ON i.evento_id = eo.evento_id AND i.status = 'ativa'
         WHERE eo.usuario_id = :usuario_id"
    );
    $consulta->execute(['usuario_id' => $organizadorId]);
    $resumo = $consulta->fetch();

    $totalInscricoes = (int) ($resumo['total_inscricoes'] ?? 0);
    $totalPresencas = (int) ($resumo['total_presencas'] ?? 0);
    $taxaPresenca = $totalInscricoes > 0 ? (int) round(($totalPresencas / $totalInscricoes) * 100) : 0;

    return [
        'inscricoes' => $totalInscricoes,
        'presencas' => $totalPresencas,
        'taxa_presenca' => $taxaPresenca,
        'eventos_concluidos' => (int) ($resumo['eventos_concluidos'] ?? 0),
        'certificados_emitidos' => (int) ($resumo['certificados_emitidos'] ?? 0),
    ];
}

function relatorioPorEvento(PDO $pdo, int $organizadorId, ?int $eventoId = null): array
{
    $sql =
        "SELECT e.id, e.titulo, e.data_inicio, e.data_fim,
                COUNT(i.id) AS total_inscricoes,
                COUNT(CASE WHEN i.presenca_confirmada = 1 THEN 1 END) AS total_presencas,
                ROUND(
                    COALESCE(
                        (COUNT(CASE WHEN i.presenca_confirmada = 1 THEN 1 END) * 100.0) / NULLIF(COUNT(i.id), 0),
                        0
                    ),
                    0
                ) AS taxa_presenca
         FROM eventos e
         JOIN evento_organizadores eo ON eo.evento_id = e.id AND eo.usuario_id = :usuario_id
         LEFT JOIN inscricoes i ON i.evento_id = e.id AND i.status = 'ativa'
         WHERE 1 = 1";

    $params = ['usuario_id' => $organizadorId];
    if ($eventoId !== null) {
        $sql .= ' AND e.id = :evento_id';
        $params['evento_id'] = $eventoId;
    }

    $sql .= ' GROUP BY e.id, e.titulo, e.data_inicio, e.data_fim ORDER BY e.data_inicio DESC, e.id DESC';
    $consulta = $pdo->prepare($sql);
    $consulta->execute($params);

    return $consulta->fetchAll();
}

function relatorioPorAtividade(PDO $pdo, int $organizadorId, ?int $eventoId = null): array
{
    $sql =
        "SELECT a.id, a.evento_id, e.titulo AS evento_titulo, a.titulo AS atividade_titulo,
                a.inicio, a.fim,
                COUNT(DISTINCT i.usuario_id) AS total_inscricoes,
                COUNT(DISTINCT pa.usuario_id) AS total_presencas,
                ROUND(
                    COALESCE(
                        (COUNT(DISTINCT pa.usuario_id) * 100.0) / NULLIF(COUNT(DISTINCT i.usuario_id), 0),
                        0
                    ),
                    0
                ) AS taxa_presenca
         FROM atividades a
         JOIN eventos e ON e.id = a.evento_id
         JOIN evento_organizadores eo ON eo.evento_id = e.id AND eo.usuario_id = :usuario_id
         LEFT JOIN inscricoes i ON i.evento_id = a.evento_id AND i.status = 'ativa'
         LEFT JOIN presencas_atividades pa ON pa.atividade_id = a.id AND pa.evento_id = a.evento_id AND pa.usuario_id = i.usuario_id
         WHERE 1 = 1";

    $params = ['usuario_id' => $organizadorId];
    if ($eventoId !== null) {
        $sql .= ' AND a.evento_id = :evento_id';
        $params['evento_id'] = $eventoId;
    }

    $sql .= ' GROUP BY a.id, a.evento_id, e.titulo, a.titulo, a.inicio, a.fim ORDER BY a.inicio DESC, a.id DESC';
    $consulta = $pdo->prepare($sql);
    $consulta->execute($params);

    return $consulta->fetchAll();
}
