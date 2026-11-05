<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$usuario = requireRole('participante');
$userId = (int) $usuario['id'];

$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM inscricoes i JOIN eventos e ON e.id = i.evento_id
     WHERE i.usuario_id = :usuario_id AND i.status = 'ativa' AND e.data_fim < NOW()"
);
$consulta->execute(['usuario_id' => $userId]);
$totalEventos = (int) $consulta->fetchColumn();
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM inscricoes
     WHERE usuario_id = :usuario_id AND status = 'ativa' AND presenca_confirmada = 1"
);
$consulta->execute(['usuario_id' => $userId]);
$totalPresencas = (int) $consulta->fetchColumn();
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM inscricoes
     WHERE usuario_id = :usuario_id AND status = 'ativa'
       AND presenca_confirmada = 1 AND certificado_url IS NOT NULL"
);
$consulta->execute(['usuario_id' => $userId]);
$totalCertificados = (int) $consulta->fetchColumn();
$consulta = $pdo->prepare(
    "SELECT e.titulo, e.categoria, e.data_inicio, e.data_fim,
            i.presenca_confirmada, i.certificado_url
     FROM inscricoes i JOIN eventos e ON e.id = i.evento_id
     WHERE i.usuario_id = :usuario_id AND i.status = 'ativa' AND e.data_fim < NOW()
     ORDER BY e.data_inicio DESC"
);
$consulta->execute(['usuario_id' => $userId]);
$historico = $consulta->fetchAll();
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
        </main>
        <?php appNavigation('historico'); ?>
    </div>
</body>
</html>
