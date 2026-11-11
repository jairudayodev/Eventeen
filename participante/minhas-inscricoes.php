<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/inscricoes.php';
require_once __DIR__ . '/../includes/financeiro.php';
$usuario = requireRole('participante');
$consulta = $pdo->prepare(
    "SELECT i.id AS inscricao_id, i.status AS inscricao_status, e.*,
            pg.status AS pagamento_status,
            (" . condicaoCancelamentoPermitido() . ") AS pode_cancelar
     FROM inscricoes i
     JOIN eventos e ON e.id = i.evento_id
     LEFT JOIN pagamentos_inscricoes pg ON pg.inscricao_id = i.id
     WHERE i.usuario_id = :usuario_id AND i.status = 'ativa' AND e.data_fim >= NOW()
     ORDER BY e.data_inicio"
);
$consulta->execute(['usuario_id' => (int) $usuario['id']]);
$inscricoes = $consulta->fetchAll();
$ativas = count($inscricoes);
$confirmacao = ($_GET['inscricao'] ?? '') === 'confirmada' ? 'Sua inscrição foi confirmada com sucesso.' : null;
$cancelamento = match ($_GET['cancelamento'] ?? '') {
    'concluido' => 'Sua inscrição foi cancelada e a vaga foi liberada.',
    'indisponivel' => 'O cancelamento só é permitido até 24 horas antes do início do evento e enquanto não houver presença registrada.',
    default => null,
};
$rotulosPagamento = ['pendente' => 'Pagamento pendente', 'confirmado' => 'Pagamento confirmado', 'estornado' => 'Pagamento estornado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Minhas inscrições | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content">
            <header class="app-header"><h1>Minhas inscrições</h1></header>
            <?php renderNotice($confirmacao); ?>
            <?php renderNotice($cancelamento, ($_GET['cancelamento'] ?? '') === 'indisponivel'); ?>
            <section class="app-card" aria-label="Resumo de inscrições">
                <div class="app-stat-inline"><span class="app-stat-inline__label">Inscrições ativas</span><span class="app-stat-inline__value"><?= $ativas ?></span></div>
            </section>
            <section class="app-section app-event-list" aria-label="Lista de inscrições">
                <?php if (!$inscricoes): ?>
                    <p class="app-empty">Você não tem inscrições ativas no momento. <a href="eventos.php">Explore os eventos</a>.</p>
                <?php endif; ?>
                <?php foreach ($inscricoes as $inscricao): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🗓️</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($inscricao['titulo']) ?></h3>
                                <div class="app-event-card__details"><span>📅 <?= h(formatDateTime($inscricao['data_inicio'])) ?></span></div>
                                <div class="app-event-card__details"><span>📍 <?= h($inscricao['local']) ?></span></div>
                                <?php if ((float) $inscricao['valor_inscricao'] > 0): ?>
                                    <div class="app-event-card__details"><span>💳 <?= h(financeiroMoeda($inscricao['valor_inscricao'])) ?> · <?= h($rotulosPagamento[$inscricao['pagamento_status'] ?? ''] ?? 'Pagamento não registrado') ?></span></div>
                                <?php endif; ?>
                                <span class="app-event-card__status">✓ Confirmada</span>
                            </div>
                        </div>
                        <?php if ((int) $inscricao['pode_cancelar'] === 1): ?>
                            <a class="app-event-card__cta" href="cancelar-inscricao.php?id=<?= (int) $inscricao['inscricao_id'] ?>">Cancelar inscrição</a>
                        <?php else: ?>
                            <span class="app-event-card__cta app-event-card__cta--disabled" title="Cancelamento permitido até 24 horas antes do início e sem presença registrada.">Cancelamento encerrado</span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
        <?php appNavigation('inscricoes'); ?>
    </div>
</body>
</html>
