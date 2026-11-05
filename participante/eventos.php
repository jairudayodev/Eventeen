<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$usuario = requireRole('participante');
$termo = trim((string) ($_GET['q'] ?? ''));
$categoria = trim((string) ($_GET['categoria'] ?? ''));

$categorias = $pdo->query(
    "SELECT DISTINCT categoria FROM eventos
     WHERE status = 'publicado' AND data_fim >= NOW()
     ORDER BY categoria"
)->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT e.*,
        (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'ativa') AS inscritos
        FROM eventos e WHERE e.status = 'publicado' AND e.data_fim >= NOW()";
$parametros = [];
if ($termo !== '') {
    $sql .= ' AND (e.titulo LIKE :termo_titulo OR e.descricao LIKE :termo_descricao OR e.local LIKE :termo_local)';
    $parametros['termo_titulo'] = '%' . $termo . '%';
    $parametros['termo_descricao'] = '%' . $termo . '%';
    $parametros['termo_local'] = '%' . $termo . '%';
}
if ($categoria !== '') {
    $sql .= ' AND e.categoria = :categoria';
    $parametros['categoria'] = $categoria;
}
$sql .= ' ORDER BY e.data_inicio';
$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$eventos = $consulta->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Eventos | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content">
            <header class="app-header"><h1>Consulta de eventos</h1></header>
            <form class="app-toolbar" method="get" action="eventos.php">
                <label class="app-search" aria-label="Buscar eventos">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="q" value="<?= h($termo) ?>" placeholder="Buscar eventos" aria-label="Buscar eventos">
                </label>
            </form>
            <div class="app-filter-list" aria-label="Filtros de eventos">
                <a class="app-filter<?= $categoria === '' ? ' app-filter--active' : '' ?>" href="eventos.php">Todos</a>
                <?php foreach ($categorias as $nomeCategoria): ?>
                    <a class="app-filter<?= $categoria === $nomeCategoria ? ' app-filter--active' : '' ?>" href="eventos.php?categoria=<?= rawurlencode($nomeCategoria) ?><?= $termo !== '' ? '&amp;q=' . rawurlencode($termo) : '' ?>"><?= h($nomeCategoria) ?></a>
                <?php endforeach; ?>
            </div>
            <section class="app-event-list" aria-label="Lista de eventos">
                <?php if (!$eventos): ?>
                    <p class="app-empty">Nenhum evento encontrado para os filtros informados.</p>
                <?php endif; ?>
                <?php foreach ($eventos as $evento):
                    $vagas = $evento['capacidade'] === null ? null : max(0, (int) $evento['capacidade'] - (int) $evento['inscritos']);
                ?>
                    <article class="app-event-card">
                        <div class="app-event-card__inner">
                            <div class="app-event-card__art" aria-hidden="true">🗓️</div>
                            <div class="app-event-card__meta">
                                <span class="app-event-card__category"><?= h($evento['categoria']) ?></span>
                                <h3 class="app-event-card__title"><?= h($evento['titulo']) ?></h3>
                                <div class="app-event-card__details"><span>📅 <?= h(formatDateTime($evento['data_inicio'])) ?></span></div>
                                <div class="app-event-card__details"><span>📍 <?= h($evento['local']) ?></span></div>
                                <span class="app-event-card__status"><?= $vagas === null ? 'Inscrições abertas' : ($vagas > 0 ? $vagas . ' vagas disponíveis' : 'Vagas esgotadas') ?></span>
                            </div>
                        </div>
                        <a class="app-event-card__cta" href="detalhes-evento.php?id=<?= (int) $evento['id'] ?>">Detalhes</a>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
        <?php appNavigation('eventos'); ?>
    </div>
</body>
</html>
