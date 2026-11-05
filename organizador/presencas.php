<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Inscrições e presenças', 'Consulte os participantes e acompanhe as confirmações de presença.', 'presencas');
?>
<div class="management-toolbar">
    <select class="management-select" aria-label="Selecionar evento" disabled><option>Semana de Tecnologia 2026</option></select>
    <input class="management-search" type="search" placeholder="Buscar participante" aria-label="Buscar participantes" disabled>
</div>
<section class="management-kpis" aria-label="Resumo de participantes">
    <?php managementKpi('94', 'Inscritos', '♙'); ?>
    <?php managementKpi('82', 'Presenças confirmadas', '✓'); ?>
    <?php managementKpi('12', 'Aguardando confirmação', '◷'); ?>
    <?php managementKpi('87%', 'Taxa de presença', '◉'); ?>
</section>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Participante</th><th>E-mail</th><th>Inscrição</th><th>Presença</th></tr></thead>
        <tbody>
            <tr><td data-label="Participante"><strong>Lucas Almeida</strong></td><td data-label="E-mail">lucas@example.com</td><td data-label="Inscrição"><span class="management-badge">Confirmada</span></td><td data-label="Presença"><span class="management-check management-check--present" aria-label="Presença confirmada">✓</span></td></tr>
            <tr><td data-label="Participante"><strong>Beatriz Santos</strong></td><td data-label="E-mail">beatriz@example.com</td><td data-label="Inscrição"><span class="management-badge">Confirmada</span></td><td data-label="Presença"><span class="management-check" aria-label="Presença pendente">—</span></td></tr>
            <tr><td data-label="Participante"><strong>Pedro Oliveira</strong></td><td data-label="E-mail">pedro@example.com</td><td data-label="Inscrição"><span class="management-badge">Confirmada</span></td><td data-label="Presença"><span class="management-check management-check--present" aria-label="Presença confirmada">✓</span></td></tr>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
