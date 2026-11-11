<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';

$usuario = requireRole('organizador');
$organizadorId = (int) $usuario['id'];

$consulta = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_eventos,
        COALESCE(SUM(e.status = 'publicado' AND e.data_fim >= NOW()), 0) AS publicados,
        COALESCE(SUM(e.status = 'rascunho'), 0) AS rascunhos,
        COALESCE(SUM(CASE WHEN e.status = 'publicado' AND e.data_fim >= NOW() THEN e.capacidade ELSE 0 END), 0) AS vagas_abertas,
        COALESCE(SUM(CASE WHEN e.status = 'publicado' AND e.data_fim >= NOW() THEN
            (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'ativa') ELSE 0 END), 0) AS inscricoes_abertas
     FROM eventos e
     JOIN evento_organizadores eo ON eo.evento_id = e.id AND eo.usuario_id = :usuario_id"
);
$consulta->execute(['usuario_id' => $organizadorId]);
$resumo = $consulta->fetch();
$ocupacao = (int) $resumo['vagas_abertas'] > 0
    ? (int) round((int) $resumo['inscricoes_abertas'] * 100 / (int) $resumo['vagas_abertas'])
    : 0;

$consulta = $pdo->prepare(
    "SELECT COUNT(*)
     FROM presencas_atividades pa
     JOIN evento_organizadores eo ON eo.evento_id = pa.evento_id AND eo.usuario_id = :usuario_id"
);
$consulta->execute(['usuario_id' => $organizadorId]);
$presencas = (int) $consulta->fetchColumn();

$consulta = $pdo->prepare(
    "SELECT e.id, e.titulo, e.local, e.data_inicio, e.status, e.capacidade,
            (SELECT COUNT(*) FROM inscricoes i WHERE i.evento_id = e.id AND i.status = 'ativa') AS inscritos
     FROM eventos e
     JOIN evento_organizadores eo ON eo.evento_id = e.id AND eo.usuario_id = :usuario_id
     WHERE e.status IN ('rascunho', 'publicado') AND e.data_fim >= NOW()
     ORDER BY e.data_inicio, e.id
     LIMIT 4"
);
$consulta->execute(['usuario_id' => $organizadorId]);
$proximos = $consulta->fetchAll();

renderManagementStart($usuario, 'Painel do organizador', 'Acompanhe seus eventos, inscrições e presenças.', 'inicio');
?>
<section class="management-kpis" aria-label="Resumo do organizador">
    <?php managementKpi((string) $resumo['publicados'], 'Eventos publicados em andamento', '▣'); ?>
    <?php managementKpi((string) $resumo['inscricoes_abertas'], 'Inscrições confirmadas', '♙'); ?>
    <?php managementKpi($ocupacao . '%', 'Ocupação média', '◉'); ?>
    <?php managementKpi((string) $presencas, 'Presenças registradas', '✓'); ?>
</section>

<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading">
            <h2>Próximos eventos</h2>
            <a href="eventos.php">Ver todos</a>
        </div>
        <div class="management-rows">
            <?php if (!$proximos): ?>
                <div class="management-row"><div class="management-row__main"><strong>Nenhum evento próximo</strong><span>Crie um evento para começar a receber inscrições.</span></div></div>
            <?php endif; ?>
            <?php foreach ($proximos as $evento): ?>
                <div class="management-row">
                    <div class="management-row__main">
                        <strong><?= h($evento['titulo']) ?></strong>
                        <span><?= h(formatDateTime($evento['data_inicio'])) ?> · <?= h($evento['local']) ?> · <?= (int) $evento['inscritos'] ?>/<?= (int) $evento['capacidade'] ?> inscritos</span>
                    </div>
                    <span class="management-badge<?= $evento['status'] === 'rascunho' ? ' management-badge--draft' : '' ?>"><?= $evento['status'] === 'rascunho' ? 'Rascunho' : 'Publicado' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="management-panel">
        <div class="management-panel__heading">
            <h2>Acesso rápido</h2>
            <a href="mais.php">Mais ferramentas</a>
        </div>
        <div class="management-feature-grid management-feature-grid--quick">
            <a class="management-feature" href="evento-form.php">
                <span class="management-feature__icon">＋</span>
                <strong>Criar evento</strong>
                <span>Prepare os dados do próximo evento.</span>
            </a>
            <a class="management-feature" href="presencas.php">
                <span class="management-feature__icon">✓</span>
                <strong>Registrar presença</strong>
                <span>Consulte os inscritos por atividade.</span>
            </a>
            <a class="management-feature" href="relatorios.php">
                <span class="management-feature__icon">▥</span>
                <strong>Relatórios</strong>
                <span>Inscrições e presenças por evento.</span>
            </a>
            <a class="management-feature" href="financeiro.php">
                <span class="management-feature__icon">R$</span>
                <strong>Financeiro</strong>
                <span>Pagamentos, despesas e saldo.</span>
            </a>
        </div>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
