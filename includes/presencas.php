<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function registrarPresencaAtividade(
    PDO $pdo,
    int $eventoId,
    int $organizadorId,
    int $atividadeId,
    int $participanteId,
    bool $presente
): void {
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT a.id
             FROM atividades a
             JOIN evento_organizadores eo
               ON eo.evento_id = a.evento_id AND eo.usuario_id = :organizador_id
             WHERE a.id = :atividade_id AND a.evento_id = :evento_id
             FOR UPDATE'
        );
        $consulta->execute([
            'organizador_id' => $organizadorId,
            'atividade_id' => $atividadeId,
            'evento_id' => $eventoId,
        ]);
        if (!$consulta->fetch()) {
            throw new DomainException('Atividade não encontrada neste evento ou você não tem permissão para registrar presenças.');
        }

        $consulta = $pdo->prepare(
            'SELECT status FROM inscricoes
             WHERE usuario_id = :usuario_id AND evento_id = :evento_id
             FOR UPDATE'
        );
        $consulta->execute(['usuario_id' => $participanteId, 'evento_id' => $eventoId]);
        if ($consulta->fetchColumn() !== 'ativa') {
            throw new DomainException('A presença exige uma inscrição Confirmada neste evento.');
        }

        if ($presente) {
            $consulta = $pdo->prepare(
                'INSERT INTO presencas_atividades (atividade_id, evento_id, usuario_id)
                 VALUES (:atividade_id, :evento_id, :usuario_id)
                 ON DUPLICATE KEY UPDATE confirmado_em = presencas_atividades.confirmado_em'
            );
            $consulta->execute([
                'atividade_id' => $atividadeId,
                'evento_id' => $eventoId,
                'usuario_id' => $participanteId,
            ]);
        } else {
            $consulta = $pdo->prepare(
                'SELECT 1 FROM certificados WHERE atividade_id = :atividade_id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['atividade_id' => $atividadeId, 'usuario_id' => $participanteId]);
            if ($consulta->fetchColumn() !== false) {
                throw new DomainException('A presença não pode ser removida porque o certificado já foi emitido.');
            }
            $consulta = $pdo->prepare(
                'DELETE FROM presencas_atividades
                 WHERE atividade_id = :atividade_id AND evento_id = :evento_id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'atividade_id' => $atividadeId,
                'evento_id' => $eventoId,
                'usuario_id' => $participanteId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function presencasAtividadesHistorico(PDO $pdo, int $participanteId): array
{
    $consulta = $pdo->prepare(
        "SELECT a.titulo AS atividade_titulo, a.inicio, a.fim,
                e.titulo AS evento_titulo, e.categoria, s.nome AS espaco_nome
         FROM presencas_atividades pa
         JOIN atividades a ON a.id = pa.atividade_id AND a.evento_id = pa.evento_id
         JOIN eventos e ON e.id = pa.evento_id
         JOIN inscricoes i
           ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id
         JOIN espacos s ON s.id = a.espaco_id AND s.evento_id = a.evento_id
         WHERE pa.usuario_id = :usuario_id AND i.status = 'ativa' AND a.fim < NOW()
         ORDER BY a.inicio DESC, a.id DESC"
    );
    $consulta->execute(['usuario_id' => $participanteId]);

    return $consulta->fetchAll();
}
