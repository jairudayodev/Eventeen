<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$usuario = requireRole('participante');
$userId = (int) $usuario['id'];

$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM inscricoes i
     JOIN eventos e ON e.id = i.evento_id
     WHERE i.usuario_id = :usuario_id AND i.status = 'ativa'
       AND e.data_fim >= NOW() AND e.status = 'publicado'"
);
$consulta->execute(['usuario_id' => $userId]);
$inscricoesAtivas = (int) $consulta->fetchColumn();

$consulta = $pdo->prepare(
    "SELECT COUNT(DISTINCT pa.evento_id)
     FROM presencas_atividades pa
     JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id
     WHERE pa.usuario_id = :usuario_id AND i.status = 'ativa'"
);
$consulta->execute(['usuario_id' => $userId]);
$participacoes = (int) $consulta->fetchColumn();

$consulta = $pdo->prepare('SELECT COUNT(*) FROM certificados WHERE usuario_id = :usuario_id');
$consulta->execute(['usuario_id' => $userId]);
$certificados = (int) $consulta->fetchColumn();

$consulta = $pdo->prepare(
    "SELECT e.*, i.id AS inscricao_id
     FROM inscricoes i JOIN eventos e ON e.id = i.evento_id
     WHERE i.usuario_id = :usuario_id AND i.status = 'ativa'
       AND e.data_fim >= NOW() AND e.status = 'publicado'
     ORDER BY e.data_inicio LIMIT 3"
);
$consulta->execute(['usuario_id' => $userId]);
$proximosEventos = $consulta->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Início | Eventeen: do convite ao certificado</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell">
        <?php appHeader($usuario); ?>
        <main class="app-content" id="conteudo">
            <header class="app-header">
                <h1>Olá, <?= h(explode(' ', displayName($usuario))[0]) ?>!</h1>
                <p class="app-header__subtitle">Seu próximo encontro começa aqui.</p>
            </header>
            <section class="app-hero" aria-label="Explorar eventos">
                <div class="app-hero__copy">
                    <h2 class="app-hero__title">Descubra eventos acadêmicos</h2>
                    <p>Palestras e oficinas na Fatec Ourinhos.</p>
                    <a class="app-hero__button" href="eventos.php">Explorar eventos</a>
                </div>
                <div class="app-hero__icon" aria-hidden="true">🗓️</div>
            </section>
            <section class="app-stat-grid" aria-label="Resumo do participante">
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $inscricoesAtivas ?></div><div class="app-stat-card__label">Inscrições</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $participacoes ?></div><div class="app-stat-card__label">Participações</div></article>
                <article class="app-stat-card"><div class="app-stat-card__value"><?= $certificados ?></div><div class="app-stat-card__label">Certificados</div></article>
            </section>
            <section class="app-section app-event-list" aria-labelledby="meus-proximos-eventos">
                <h2 id="meus-proximos-eventos" class="app-section__title">Meus próximos eventos</h2>
                <?php if (!$proximosEventos): ?>
                    <p class="app-empty">Você ainda não tem eventos próximos inscritos. <a href="eventos.php">Encontre um evento</a>.</p>
                <?php endif; ?>
                <?php foreach ($proximosEventos as $evento): ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🗓️</div>
                            <div class="app-event-card__meta">
                                <h3 class="app-event-card__title"><?= h($evento['titulo']) ?></h3>
                                <div class="app-event-card__details"><span>📅 <?= h(formatPeriodo($evento['data_inicio'], $evento['data_fim'])) ?></span></div>
                                <div class="app-event-card__details"><span>📍 <?= h($evento['local']) ?></span></div>
                                <span class="app-event-card__status">✓ Inscrição confirmada</span>
                            </div>
                        </div>
                        <a class="app-event-card__cta" href="detalhes-evento.php?id=<?= (int) $evento['id'] ?>">Ver evento</a>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
        <?php appNavigation('inicio'); ?>
    </div>
</body>
</html>
