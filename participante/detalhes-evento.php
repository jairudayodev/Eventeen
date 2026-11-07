<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$usuario = requireRole('participante');
$eventoId = filter_var($_GET['id'] ?? $_POST['evento_id'] ?? null, FILTER_VALIDATE_INT);
if (!$eventoId || $eventoId < 1) {
    header('Location: eventos.php');
    exit;
}

$mensagem = null;
$erro = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    try {
        $pdo->beginTransaction();
        $consulta = $pdo->prepare('SELECT * FROM eventos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $eventoId]);
        $eventoBloqueado = $consulta->fetch();
        if (!$eventoBloqueado || $eventoBloqueado['status'] !== 'publicado' || strtotime($eventoBloqueado['data_inicio']) <= time()) {
            throw new DomainException('Este evento não está mais aceitando inscrições.');
        }

        $consulta = $pdo->prepare(
            "SELECT id, status FROM inscricoes WHERE usuario_id = :usuario_id AND evento_id = :evento_id"
        );
        $consulta->execute(['usuario_id' => (int) $usuario['id'], 'evento_id' => $eventoId]);
        $inscricaoExistente = $consulta->fetch();
        if ($inscricaoExistente && $inscricaoExistente['status'] === 'ativa') {
            throw new DomainException('Você já está inscrito neste evento.');
        }

        $consulta = $pdo->prepare("SELECT COUNT(*) FROM inscricoes WHERE evento_id = :evento_id AND status = 'ativa'");
        $consulta->execute(['evento_id' => $eventoId]);
        $inscritos = (int) $consulta->fetchColumn();
        if ($eventoBloqueado['capacidade'] !== null && $inscritos >= (int) $eventoBloqueado['capacidade']) {
            throw new DomainException('As vagas deste evento estão esgotadas.');
        }

        $consulta = $pdo->prepare(
            "INSERT INTO inscricoes (usuario_id, evento_id, status)
             VALUES (:usuario_id, :evento_id, 'ativa')
             ON DUPLICATE KEY UPDATE
                status = 'ativa',
                presenca_confirmada = 0,
                certificado_url = NULL,
                inscrito_em = CURRENT_TIMESTAMP"
        );
        $consulta->execute(['usuario_id' => (int) $usuario['id'], 'evento_id' => $eventoId]);
        $pdo->commit();
        header('Location: minhas-inscricoes.php?inscricao=confirmada');
        exit;
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem = $exception->getMessage();
        $erro = true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$consulta = $pdo->prepare(
    "SELECT e.*,
        (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'ativa') AS inscritos,
        (SELECT i.status FROM inscricoes i WHERE i.evento_id = e.id AND i.usuario_id = :usuario_id LIMIT 1) AS minha_inscricao
     FROM eventos e WHERE e.id = :id AND e.status = 'publicado'"
);
$consulta->execute(['usuario_id' => (int) $usuario['id'], 'id' => $eventoId]);
$evento = $consulta->fetch();
if (!$evento) {
    http_response_code(404);
    exit('Evento não encontrado.');
}
$vagas = $evento['capacidade'] === null ? null : max(0, (int) $evento['capacidade'] - (int) $evento['inscritos']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title><?= h($evento['titulo']) ?> | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content">
            <?php renderNotice($mensagem, $erro); ?>
            <section class="app-surface" aria-label="Detalhes do evento">
                <div class="app-event-hero">
                    <div class="app-event-hero__badge" aria-hidden="true">✦</div>
                    <div class="app-event-hero__content">
                        <p class="app-event-hero__eyebrow"><?= h($evento['categoria']) ?></p>
                        <h2><?= h($evento['titulo']) ?></h2>
                    </div>
                </div>
                <p class="app-event-description"><?= nl2br(h($evento['descricao'])) ?></p>
                <div class="app-detail-list">
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">📅</div><div><strong>Início</strong><?= h(formatDateTime($evento['data_inicio'])) ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">⏱️</div><div><strong>Encerramento</strong><?= h(formatDateTime($evento['data_fim'])) ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">📍</div><div><strong>Local</strong><?= h($evento['local']) ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">🎟️</div><div><strong>Vagas</strong><?= $vagas === null ? 'Sem limite informado' : (int) $vagas . ' disponíveis' ?></div></div>
                </div>
                <div class="app-action-group">
                    <?php if ($evento['minha_inscricao'] === 'ativa'): ?>
                        <a class="app-button app-button--secondary" href="minhas-inscricoes.php">Você já está inscrito</a>
                    <?php elseif (strtotime($evento['data_inicio']) > time() && ($vagas === null || $vagas > 0)): ?>
                        <form method="post" action="detalhes-evento.php?id=<?= (int) $evento['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                            <button class="app-button" type="submit">Inscrever-se</button>
                        </form>
                    <?php else: ?>
                        <span class="app-button app-button--secondary">Inscrições encerradas</span>
                    <?php endif; ?>
                    <a class="app-button app-button--ghost" href="eventos.php">Voltar aos eventos</a>
                </div>
            </section>
        </main>
        <?php appNavigation('eventos'); ?>
    </div>
</body>
</html>
