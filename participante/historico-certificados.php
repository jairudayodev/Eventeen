<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/relatorios.php';
$usuario = requireRole('participante');
$userId = (int) $usuario['id'];

$historico = historicoEventosParticipante($pdo, $userId);
$atividadesHistorico = historicoAtividadesParticipante($pdo, $userId);
$totalEventos = count($historico);
$totalPresencas = count(array_filter($historico, static fn (array $evento): bool => (int) $evento['presenca_confirmada'] === 1));
$totalCertificados = count(array_filter($historico, static fn (array $evento): bool => (int) $evento['presenca_confirmada'] === 1 && !empty($evento['certificado_url'])));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Histórico e certificados | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content">
            <header class="app-header">
                <h1>Histórico de participação</h1>
                <p class="app-header__subtitle">Fatec Ourinhos</p>
            </header>
            <section class="app-stat-grid app-stat-grid--horizontal" aria-label="Estatísticas do histórico">
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalEventos ?></div><div class="app-stat-card__label">Eventos</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalPresencas ?></div><div class="app-stat-card__label">Presenças</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= count($atividadesHistorico) ?></div><div class="app-stat-card__label">Atividades</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalCertificados ?></div><div class="app-stat-card__label">Certificados</div></article>
            </section>
            <section class="app-section app-event-list" aria-label="Eventos participados">
                <?php if (!$historico): ?><p class="app-empty">Seus eventos concluídos aparecerão aqui após a confirmação de presença.</p><?php endif; ?>
                <?php foreach ($historico as $evento): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🎓</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($evento['titulo']) ?></h3>
                                <div class="app-event-card__details"><span>📅 <?= h(date('d/m/Y', strtotime($evento['data_inicio']))) ?></span><span><?= h($evento['categoria']) ?></span></div>
                                <span class="app-event-card__status"><?= (int) $evento['presenca_confirmada'] === 1 ? '✓ Presença confirmada' : '✕ Presença não confirmada' ?></span>
                            </div>
                        </div>
                        <?php if ((int) $evento['presenca_confirmada'] === 1 && $evento['certificado_url']): ?>
                            <a class="app-event-card__cta" href="<?= h($evento['certificado_url']) ?>" target="_blank" rel="noopener">Baixar certificado</a>
                        <?php else: ?>
                            <span class="app-event-card__cta app-event-card__cta--disabled">Certificado indisponível</span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
            <section class="app-section app-event-list" aria-label="Atividades com presença confirmada">
                <header class="app-header"><h2>Atividades com presença confirmada</h2></header>
                <?php if (!$atividadesHistorico): ?><p class="app-empty">Suas atividades concluídas aparecerão aqui após a confirmação de presença.</p><?php endif; ?>
                <?php foreach ($atividadesHistorico as $atividade): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">✓</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($atividade['atividade_titulo']) ?></h3>
                                <div class="app-event-card__details">
                                    <span><?= h($atividade['evento_titulo']) ?></span>
                                    <span><?= h(date('d/m/Y', strtotime($atividade['inicio']))) ?> · <?= h(date('H:i', strtotime($atividade['inicio']))) ?>–<?= h(date('H:i', strtotime($atividade['fim']))) ?></span>
                                    <span><?= h($atividade['espaco_nome']) ?></span>
                                </div>
                                <span class="app-event-card__status">✓ Presença confirmada</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
        <?php appNavigation('historico'); ?>
    </div>
</body>
</html>
