<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/relatorios.php';
require_once __DIR__ . '/../includes/certificados.php';
$usuario = requireRole('participante');
$userId = (int) $usuario['id'];

$aviso = $_SESSION['aviso_certificado'] ?? null;
unset($_SESSION['aviso_certificado']);

$historico = historicoEventosParticipante($pdo, $userId);
$atividadesHistorico = historicoAtividadesParticipante($pdo, $userId);
$totalEventos = count($historico);
$totalEventosPresentes = count(array_filter($historico, static fn (array $evento): bool => (int) $evento['atividades_presentes'] > 0));
$totalCertificados = count(array_filter($atividadesHistorico, static fn (array $atividade): bool => !empty($atividade['codigo_validacao'])));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Histórico e certificados | Eventeen: do convite ao certificado</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content" id="conteudo">
            <header class="app-header">
                <h1>Histórico de participação</h1>
                <p class="app-header__subtitle">Fatec Ourinhos</p>
            </header>
            <?php renderNotice(is_string($aviso) ? $aviso : null, true); ?>
            <section class="app-stat-grid app-stat-grid--horizontal" aria-label="Estatísticas do histórico">
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalEventos ?></div><div class="app-stat-card__label">Eventos</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalEventosPresentes ?></div><div class="app-stat-card__label">Com presença</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= count($atividadesHistorico) ?></div><div class="app-stat-card__label">Atividades</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $totalCertificados ?></div><div class="app-stat-card__label">Certificados</div></article>
            </section>

            <section class="app-section app-event-list" aria-label="Atividades com presença confirmada">
                <header class="app-header"><h2>Atividades e certificados</h2></header>
                <?php if (!$atividadesHistorico): ?><p class="app-empty">As atividades concluídas com presença confirmada aparecerão aqui, com a opção de emitir o certificado.</p><?php endif; ?>
                <?php foreach ($atividadesHistorico as $atividade): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🎓</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($atividade['atividade_titulo']) ?></h3>
                                <div class="app-event-card__details">
                                    <span><?= h($atividade['evento_titulo']) ?></span>
                                    <span><?= h(date('d/m/Y', strtotime($atividade['inicio']))) ?> · <?= h(date('H:i', strtotime($atividade['inicio']))) ?>–<?= h(date('H:i', strtotime($atividade['fim']))) ?></span>
                                    <?php if ($atividade['espaco_nome']): ?><span><?= h($atividade['espaco_nome']) ?></span><?php endif; ?>
                                </div>
                                <span class="app-event-card__status">✓ Presença confirmada<?= $atividade['codigo_validacao'] ? ' · Código ' . h(formatarCodigoCertificado($atividade['codigo_validacao'])) : '' ?></span>
                            </div>
                        </div>
                        <?php if ($atividade['codigo_validacao']): ?>
                            <a class="app-event-card__cta" href="certificado.php?codigo=<?= h(rawurlencode($atividade['codigo_validacao'])) ?>" target="_blank" rel="noopener">Baixar certificado</a>
                        <?php else: ?>
                            <form method="post" action="certificado.php" target="_blank">
                                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                <input type="hidden" name="atividade_id" value="<?= (int) $atividade['atividade_id'] ?>">
                                <button class="app-event-card__cta" type="submit">Emitir certificado</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="app-section app-event-list" aria-label="Eventos concluídos">
                <header class="app-header"><h2>Eventos concluídos</h2></header>
                <?php if (!$historico): ?><p class="app-empty">Os eventos concluídos em que você esteve inscrito aparecerão aqui.</p><?php endif; ?>
                <?php foreach ($historico as $evento): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🗓️</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($evento['titulo']) ?></h3>
                                <div class="app-event-card__details"><span>📅 <?= h(date('d/m/Y', strtotime($evento['data_inicio']))) ?></span><span><?= h($evento['categoria']) ?></span></div>
                                <span class="app-event-card__status"><?= (int) $evento['atividades_presentes'] > 0
                                    ? '✓ Presença em ' . (int) $evento['atividades_presentes'] . ' atividade(s)'
                                    : '✕ Nenhuma presença registrada' ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
            <p class="app-empty">Qualquer pessoa pode conferir um certificado em <a href="<?= h(applicationBasePath()) ?>/validar-certificado.php">Validar certificado</a>.</p>
        </main>
        <?php appNavigation('historico'); ?>
    </div>
</body>
</html>
