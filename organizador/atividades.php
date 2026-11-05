<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Atividades e programação', 'Monte a agenda do evento e confira os horários planejados.', 'mais');
?>
<div class="management-toolbar">
    <select class="management-select" aria-label="Selecionar evento" disabled><option>Semana de Tecnologia 2026</option></select>
    <button class="management-button" type="button" disabled>＋ Adicionar atividade</button>
</div>
<section class="management-panel">
    <div class="management-panel__heading"><h2>Quarta-feira, 22 de outubro</h2><span class="management-badge">3 atividades</span></div>
    <div class="management-rows">
        <div class="management-row">
            <div class="management-row__main"><strong>Credenciamento</strong><span>18h00–19h00 · Hall de entrada</span></div>
            <span class="management-badge management-badge--muted">Recepção</span>
        </div>
        <div class="management-row">
            <div class="management-row__main"><strong>O futuro da tecnologia</strong><span>19h00–20h00 · Auditório · Marina Costa</span></div>
            <span class="management-badge">Palestra</span>
        </div>
        <div class="management-row">
            <div class="management-row__main"><strong>Conexões e ideias</strong><span>20h15–21h00 · Auditório · Rafael Lima</span></div>
            <span class="management-badge">Mesa-redonda</span>
        </div>
    </div>
</section>
<?php renderManagementEnd($usuario); ?>
