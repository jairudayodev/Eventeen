<?php
declare(strict_types=1);

function inscreverParticipante(PDO $pdo, int $usuarioId, int $eventoId): void
{
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT status, capacidade, (data_inicio > NOW()) AS aceita_inscricao
             FROM eventos WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $eventoId]);
        $evento = $consulta->fetch();
        if (!$evento || $evento['status'] !== 'publicado' || (int) $evento['aceita_inscricao'] !== 1) {
            throw new DomainException('Este evento não está mais aceitando inscrições.');
        }

        $consulta = $pdo->prepare(
            'SELECT id, status FROM inscricoes
             WHERE usuario_id = :usuario_id AND evento_id = :evento_id FOR UPDATE'
        );
        $consulta->execute(['usuario_id' => $usuarioId, 'evento_id' => $eventoId]);
        $inscricaoExistente = $consulta->fetch();
        if ($inscricaoExistente && $inscricaoExistente['status'] === 'ativa') {
            throw new DomainException('Você já está inscrito neste evento.');
        }

        $consulta = $pdo->prepare(
            "SELECT COUNT(*) FROM inscricoes
             WHERE evento_id = :evento_id AND status = 'ativa'"
        );
        $consulta->execute(['evento_id' => $eventoId]);
        $inscritos = (int) $consulta->fetchColumn();
        if ($evento['capacidade'] !== null && $inscritos >= (int) $evento['capacidade']) {
            throw new DomainException('As vagas deste evento estão esgotadas.');
        }

        $consulta = $pdo->prepare(
            "INSERT INTO inscricoes (usuario_id, evento_id, status)
             VALUES (:usuario_id, :evento_id, 'ativa')
             ON DUPLICATE KEY UPDATE
                status = 'ativa',
                inscrito_em = CURRENT_TIMESTAMP"
        );
        $consulta->execute(['usuario_id' => $usuarioId, 'evento_id' => $eventoId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Antecedência mínima, em horas, para o participante cancelar a própria inscrição (RN11).
 */
const ANTECEDENCIA_CANCELAMENTO_HORAS = 24;

/**
 * Condição SQL reutilizada na interface e na regra: evento ainda não iniciado,
 * com pelo menos 24 horas de antecedência e sem presença registrada.
 * Espera os aliases "i" (inscricoes) e "e" (eventos).
 */
function condicaoCancelamentoPermitido(): string
{
    return "i.status = 'ativa'
        AND e.data_inicio > DATE_ADD(NOW(), INTERVAL " . ANTECEDENCIA_CANCELAMENTO_HORAS . " HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM presencas_atividades pa
            WHERE pa.usuario_id = i.usuario_id AND pa.evento_id = i.evento_id
        )";
}

function cancelarInscricao(PDO $pdo, int $usuarioId, int $inscricaoId): bool
{
    $consulta = $pdo->prepare(
        'SELECT evento_id FROM inscricoes WHERE id = :id AND usuario_id = :usuario_id'
    );
    $consulta->execute(['id' => $inscricaoId, 'usuario_id' => $usuarioId]);
    $eventoId = $consulta->fetchColumn();
    if ($eventoId === false) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        // Bloqueia o evento para serializar o cancelamento com novas inscrições.
        $consulta = $pdo->prepare('SELECT id FROM eventos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => (int) $eventoId]);
        if (!$consulta->fetch()) {
            $pdo->rollBack();
            return false;
        }

        $consulta = $pdo->prepare(
            "UPDATE inscricoes i JOIN eventos e ON e.id = i.evento_id
             SET i.status = 'cancelada'
             WHERE i.id = :id AND i.usuario_id = :usuario_id
               AND i.evento_id = :evento_id
               AND " . condicaoCancelamentoPermitido()
        );
        $consulta->execute([
            'id' => $inscricaoId,
            'usuario_id' => $usuarioId,
            'evento_id' => (int) $eventoId,
        ]);
        $cancelado = $consulta->rowCount() === 1;
        $pdo->commit();

        return $cancelado;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
