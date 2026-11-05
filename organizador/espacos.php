<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Espaços disponíveis', 'Consulte os locais e suas capacidades antes de agendar atividades.', 'mais');
?>
<div class="management-toolbar">
    <span class="management-muted">Disponibilidade ilustrativa para outubro</span>
    <button class="management-button" type="button" disabled>＋ Cadastrar espaço</button>
</div>
<div class="management-columns">
    <article class="management-panel">
        <div class="management-panel__heading"><h2>Auditório</h2><span class="management-badge">Disponível</span></div>
        <div class="management-rows">
            <div class="management-row"><span class="management-muted">Capacidade</span><strong>120 pessoas</strong></div>
            <div class="management-row"><span class="management-muted">Recursos</span><strong>Projetor · Áudio</strong></div>
            <div class="management-row"><span class="management-muted">Próxima reserva</span><strong>22 out · 19h</strong></div>
        </div>
    </article>
    <article class="management-panel">
        <div class="management-panel__heading"><h2>Laboratório 2</h2><span class="management-badge">Disponível</span></div>
        <div class="management-rows">
            <div class="management-row"><span class="management-muted">Capacidade</span><strong>30 pessoas</strong></div>
            <div class="management-row"><span class="management-muted">Recursos</span><strong>Computadores · Wi-Fi</strong></div>
            <div class="management-row"><span class="management-muted">Próxima reserva</span><strong>24 out · 14h</strong></div>
        </div>
    </article>
    <article class="management-panel">
        <div class="management-panel__heading"><h2>Sala 3</h2><span class="management-badge management-badge--draft">Reservada</span></div>
        <div class="management-rows">
            <div class="management-row"><span class="management-muted">Capacidade</span><strong>40 pessoas</strong></div>
            <div class="management-row"><span class="management-muted">Recursos</span><strong>TV · Quadro branco</strong></div>
            <div class="management-row"><span class="management-muted">Reserva</span><strong>18 nov · 15h</strong></div>
        </div>
    </article>
</div>
<?php renderManagementEnd($usuario); ?>
