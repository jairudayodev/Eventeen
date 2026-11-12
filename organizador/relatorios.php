<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/relatorios.php';

$usuario = requireRole('organizador');
$organizadorId = (int) $usuario['id'];
$filtros = relatorioFiltros($_GET);

$todosEventos = relatorioPorEvento($pdo, $organizadorId);
$eventos = relatorioPorEvento($pdo, $organizadorId, $filtros);
$atividades = relatorioPorAtividade($pdo, $organizadorId, $filtros);
$resumo = relatorioResumo($eventos);
$statusRotulos = ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado', 'encerrado' => 'Encerrado'];

renderManagementStart($usuario, 'Relatórios', 'Inscrições por evento e presenças por atividade, com filtro de período.', 'mais');
?>
<form class="management-toolbar" method="get" action="relatorios.php">
    <div class="management-actions management-event-filters">
        <select id="evento-relatorio" class="management-select" name="evento_id" aria-label="Evento">
            <option value="">Todos os eventos</option>
            <?php foreach ($todosEventos as $evento): ?>
                <option value="<?= (int) $evento['id'] ?>"<?= $filtros['evento_id'] === (int) $evento['id'] ? ' selected' : '' ?>><?= h(managementRotuloEvento($evento)) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="management-muted" for="relatorio-de">De</label>
        <input id="relatorio-de" class="management-input" type="date" name="de" value="<?= h($filtros['de']) ?>">
        <label class="management-muted" for="relatorio-ate">Até</label>
        <input id="relatorio-ate" class="management-input" type="date" name="ate" value="<?= h($filtros['ate']) ?>">
        <button class="management-button management-button--secondary" type="submit">Aplicar filtro</button>
    </div>
    <button class="management-button" type="button" onclick="window.print()">Imprimir</button>
</form>
<section class="management-kpis" aria-label="Resumo dos relatórios">
    <?php managementKpi((string) $resumo['confirmadas'], 'Inscrições confirmadas', '♙'); ?>
    <?php managementKpi((string) $resumo['canceladas'], 'Inscrições canceladas', '✕'); ?>
    <?php managementKpi($resumo['taxa_presenca'] . '%', 'Presença (participantes distintos)', '✓'); ?>
    <?php managementKpi((string) $resumo['certificados'], 'Certificados emitidos', '★'); ?>
</section>

<section class="management-panel">
    <div class="management-panel__heading"><h2>Inscrições por evento</h2><span class="management-badge"><?= count($eventos) ?> evento(s)</span></div>
    <div class="management-table-wrap">
        <table class="management-table">
            <thead><tr><th>Evento</th><th>Data</th><th>Vagas</th><th>Confirmadas</th><th>Canceladas</th><th>Presentes</th><th>Presença</th></tr></thead>
            <tbody>
                <?php if (!$eventos): ?>
                    <tr><td colspan="7">Nenhum evento encontrado para os filtros informados.</td></tr>
                <?php endif; ?>
                <?php foreach ($eventos as $evento): ?>
                    <tr>
                        <td data-label="Evento"><strong><?= h($evento['titulo']) ?></strong><br><span class="management-muted"><?= h($statusRotulos[$evento['status']] ?? $evento['status']) ?> · <?= (int) $evento['atividades'] ?> atividade(s)</span></td>
                        <td data-label="Data"><?= h(date('d/m/Y H:i', strtotime((string) $evento['data_inicio']))) ?></td>
                        <td data-label="Vagas"><?= $evento['capacidade'] === null ? 'Sem limite' : (int) $evento['capacidade'] ?></td>
                        <td data-label="Confirmadas"><?= (int) $evento['confirmadas'] ?></td>
                        <td data-label="Canceladas"><?= (int) $evento['canceladas'] ?></td>
                        <td data-label="Presentes"><?= (int) $evento['presentes'] ?></td>
                        <td data-label="Presença">
                            <div class="management-progress"><span style="width: <?= min((int) $evento['taxa_presenca'], 100) ?>%"></span></div>
                            <?= (int) $evento['taxa_presenca'] ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="management-panel">
    <div class="management-panel__heading"><h2>Presenças por atividade</h2><span class="management-badge"><?= count($atividades) ?> atividade(s)</span></div>
    <div class="management-table-wrap">
        <table class="management-table">
            <thead><tr><th>Atividade</th><th>Evento</th><th>Horário</th><th>Inscritos no evento</th><th>Presenças</th><th>Presença</th></tr></thead>
            <tbody>
                <?php if (!$atividades): ?>
                    <tr><td colspan="6">Sem atividades para os filtros informados.</td></tr>
                <?php endif; ?>
                <?php foreach ($atividades as $atividade): ?>
                    <tr>
                        <td data-label="Atividade"><strong><?= h($atividade['atividade_titulo']) ?></strong><br><span class="management-muted"><?= h($atividade['tipo']) ?></span></td>
                        <td data-label="Evento"><?= h($atividade['evento_titulo']) ?></td>
                        <td data-label="Horário"><?= h(date('d/m/Y H:i', strtotime((string) $atividade['inicio']))) ?>–<?= h(date('H:i', strtotime((string) $atividade['fim']))) ?></td>
                        <td data-label="Inscritos no evento"><?= (int) $atividade['inscritos'] ?></td>
                        <td data-label="Presenças"><?= (int) $atividade['presencas'] ?></td>
                        <td data-label="Presença">
                            <div class="management-progress"><span style="width: <?= min((int) $atividade['taxa_presenca'], 100) ?>%"></span></div>
                            <?= (int) $atividade['taxa_presenca'] ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderManagementEnd($usuario); ?>
