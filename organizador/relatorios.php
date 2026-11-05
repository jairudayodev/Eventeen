<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Relatórios', 'Acompanhe inscrições, presença e desempenho dos eventos.', 'mais');
?>
<div class="management-toolbar">
    <select class="management-select" aria-label="Período do relatório" disabled><option>Últimos 30 dias</option></select>
    <button class="management-button management-button--secondary" type="button" disabled>⇩ Exportar relatório</button>
</div>
<section class="management-kpis" aria-label="Resumo dos relatórios">
    <?php managementKpi('326', 'Inscrições no período', '♙'); ?>
    <?php managementKpi('82%', 'Presença média', '✓'); ?>
    <?php managementKpi('8', 'Eventos realizados', '▣'); ?>
    <?php managementKpi('4,7', 'Avaliação média', '★'); ?>
</section>
<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Inscrições por evento</h2><span class="management-badge management-badge--muted">Exemplo</span></div>
        <div class="management-rows">
            <div class="management-row"><div class="management-row__main"><strong>Semana de Tecnologia</strong><span>94 inscrições</span><div class="management-progress"><span style="width: 78%"></span></div></div></div>
            <div class="management-row"><div class="management-row__main"><strong>Oficina de Dados</strong><span>24 inscrições</span><div class="management-progress"><span style="width: 62%"></span></div></div></div>
            <div class="management-row"><div class="management-row__main"><strong>Conexões Inteligentes</strong><span>58 inscrições</span><div class="management-progress"><span style="width: 48%"></span></div></div></div>
        </div>
    </section>
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Indicadores</h2></div>
        <div class="management-rows">
            <div class="management-row"><div class="management-row__main"><strong>Vagas ocupadas</strong><span>176 de 220 vagas disponíveis</span></div><span class="management-badge">80%</span></div>
            <div class="management-row"><div class="management-row__main"><strong>Certificados emitidos</strong><span>Após presença confirmada</span></div><strong>148</strong></div>
            <div class="management-row"><div class="management-row__main"><strong>Cancelamentos</strong><span>Inscrições canceladas</span></div><strong>11</strong></div>
        </div>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
