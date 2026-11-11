<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/relatorios.php';

$usuario = requireRole('organizador');
$organizadorId = (int) $usuario['id'];
$eventoId = $_GET['evento_id'] ?? null;
$eventoSelecionado = $eventoId !== null && $eventoId !== '' ? (int) $eventoId : null;

$resumo = relatorioResumoOrganizador($pdo, $organizadorId);
$eventos = relatorioPorEvento($pdo, $organizadorId, $eventoSelecionado);
$atividades = relatorioPorAtividade($pdo, $organizadorId, $eventoSelecionado);

renderManagementStart($usuario, 'Relatórios', 'Acompanhe inscrições, presença e desempenho dos eventos.', 'mais', false);
?>
<form class="management-toolbar" method="get" action="relatorios.php">
    <label class="management-muted" for="evento-relatorio">Evento</label>
    <select id="evento-relatorio" class="management-select" name="evento_id">
        <option value="">Todos os eventos</option>
        <?php foreach (relatorioPorEvento($pdo, $organizadorId) as $evento): ?>
            <option value="<?= (int) $evento['id'] ?>"<?= $eventoSelecionado !== null && (int) $evento['id'] === $eventoSelecionado ? ' selected' : '' ?>><?= h($evento['titulo']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="management-button management-button--secondary" type="submit">Aplicar filtro</button>
</form>
<section class="management-kpis" aria-label="Resumo dos relatórios">
    <?php managementKpi((string) $resumo['inscricoes'], 'Inscrições', '♙'); ?>
    <?php managementKpi((string) $resumo['taxa_presenca'] . '%', 'Presença média', '✓'); ?>
    <?php managementKpi((string) $resumo['eventos_concluidos'], 'Eventos concluídos', '▣'); ?>
    <?php managementKpi((string) $resumo['certificados_emitidos'], 'Certificados emitidos', '★'); ?>
</section>
<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Inscrições por evento</h2></div>
        <div class="management-rows">
            <?php if (!$eventos): ?>
                <div class="management-row"><div class="management-row__main"><strong>Nenhum evento encontrado</strong><span>Cadastre eventos e inscrições para acompanhar o desempenho.</span></div></div>
            <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                    <?php $taxaEvento = (int) ($evento['taxa_presenca'] ?? 0); ?>
                    <div class="management-row">
                        <div class="management-row__main">
                            <strong><?= h($evento['titulo']) ?></strong>
                            <span><?= (int) $evento['total_inscricoes'] ?> inscrições · <?= (int) $evento['total_presencas'] ?> presenças</span>
                            <div class="management-progress"><span style="width: <?= min($taxaEvento, 100) ?>%"></span></div>
                        </div>
                        <span class="management-badge"><?= $taxaEvento ?>%</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Atividades por evento</h2></div>
        <div class="management-rows">
            <?php if (!$atividades): ?>
                <div class="management-row"><div class="management-row__main"><strong>Sem atividades registradas</strong><span>As presenças por atividade aparecerão aqui após o cadastro.</span></div></div>
            <?php else: ?>
                <?php foreach ($atividades as $atividade): ?>
                    <?php $taxaAtividade = (int) ($atividade['taxa_presenca'] ?? 0); ?>
                    <div class="management-row">
                        <div class="management-row__main">
                            <strong><?= h($atividade['atividade_titulo']) ?></strong>
                            <span><?= h($atividade['evento_titulo']) ?> · <?= (int) $atividade['total_inscricoes'] ?> inscritos</span>
                            <div class="management-progress"><span style="width: <?= min($taxaAtividade, 100) ?>%"></span></div>
                        </div>
                        <span class="management-badge"><?= $taxaAtividade ?>%</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
