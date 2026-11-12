<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/inscricoes.php';
require_once __DIR__ . '/../includes/financeiro.php';
require __DIR__ . '/../includes/email.php';
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
        inscreverParticipante($pdo, (int) $usuario['id'], $eventoId);
        notificarInscricaoConfirmada($pdo, $usuario, $eventoId);
        header('Location: minhas-inscricoes.php?inscricao=confirmada');
        exit;
    } catch (DomainException $exception) {
        $mensagem = $exception->getMessage();
        $erro = true;
    }
}

$consulta = $pdo->prepare(
    "SELECT e.*, (e.data_inicio > NOW()) AS aceita_inscricao,
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

$consulta = $pdo->prepare(
    "SELECT a.id, a.titulo, a.tipo, a.descricao, a.inicio, a.fim, s.nome AS espaco_nome,
            (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
             FROM atividade_palestrantes ap
             JOIN palestrantes p ON p.id = ap.palestrante_id AND p.evento_id = ap.evento_id
             WHERE ap.atividade_id = a.id) AS palestrantes
     FROM atividades a
     JOIN espacos s ON s.id = a.espaco_id AND s.evento_id = a.evento_id
     WHERE a.evento_id = :evento_id
     ORDER BY a.inicio, a.id"
);
$consulta->execute(['evento_id' => (int) $evento['id']]);
$programacao = $consulta->fetchAll();
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
        <main class="app-content" id="conteudo">
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
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">📅</div><div><strong>Data e horário</strong><?= h(formatPeriodo($evento['data_inicio'], $evento['data_fim'])) ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">📍</div><div><strong>Local</strong><?= h($evento['local']) ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">🎟️</div><div><strong>Vagas</strong><?= $vagas === null ? 'Sem limite informado' : ($vagas > 0 ? (int) $vagas . ' disponíveis' : 'Vagas esgotadas') ?></div></div>
                    <div class="app-detail-item"><div class="app-detail-item__icon" aria-hidden="true">💳</div><div><strong>Inscrição</strong><?= (float) $evento['valor_inscricao'] > 0 ? h(financeiroMoeda($evento['valor_inscricao'])) . ' · pagamento registrado pela organização' : 'Gratuita' ?></div></div>
                </div>
                <?php
                $motivoFechado = (int) $evento['aceita_inscricao'] !== 1
                    ? 'As inscrições se encerram no início do evento.'
                    : 'Todas as vagas deste evento já foram preenchidas.';
                ?>
                <div class="app-action-group">
                    <?php if ($evento['minha_inscricao'] === 'ativa'): ?>
                        <a class="app-button app-button--secondary" href="minhas-inscricoes.php">✓ Você está inscrito · ver inscrição</a>
                    <?php elseif ((int) $evento['aceita_inscricao'] === 1 && ($vagas === null || $vagas > 0)): ?>
                        <form method="post" action="detalhes-evento.php?id=<?= (int) $evento['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                            <button class="app-button" type="submit"><?= $evento['minha_inscricao'] === 'cancelada' ? 'Inscrever-se novamente' : 'Inscrever-se' ?></button>
                        </form>
                    <?php else: ?>
                        <span class="app-button app-button--secondary" aria-disabled="true">Inscrições encerradas</span>
                    <?php endif; ?>
                    <a class="app-button app-button--ghost" href="eventos.php">Voltar aos eventos</a>
                </div>
                <?php if ($evento['minha_inscricao'] !== 'ativa' && !((int) $evento['aceita_inscricao'] === 1 && ($vagas === null || $vagas > 0))): ?>
                    <p class="app-results-count"><?= h($motivoFechado) ?></p>
                <?php elseif ($evento['minha_inscricao'] !== 'ativa'): ?>
                    <p class="app-results-count">Você poderá cancelar a inscrição até 24 horas antes do início do evento.</p>
                <?php endif; ?>
                <section class="app-section" aria-labelledby="programacao-evento">
                    <h3 id="programacao-evento" class="app-section__title">Programação</h3>
                    <?php if (!$programacao): ?>
                        <p class="app-empty">A programação deste evento ainda não foi publicada.</p>
                    <?php endif; ?>
                    <?php foreach ($programacao as $atividade): ?>
                        <div class="app-program-item">
                            <strong><?= h(date('d/m', strtotime($atividade['inicio']))) ?> · <?= h(date('H\hi', strtotime($atividade['inicio']))) ?>–<?= h(date('H\hi', strtotime($atividade['fim']))) ?> · <?= h($atividade['titulo']) ?></strong>
                            <span><?= h($atividade['tipo']) ?> · <?= h($atividade['espaco_nome']) ?><?= $atividade['palestrantes'] ? ' · ' . h($atividade['palestrantes']) : '' ?></span>
                        </div>
                    <?php endforeach; ?>
                </section>
            </section>
        </main>
        <?php appNavigation('eventos'); ?>
    </div>
</body>
</html>
