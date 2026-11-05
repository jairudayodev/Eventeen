<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Parceiros e patrocínios', 'Organize os apoiadores e as cotas vinculadas aos eventos.', 'mais');
?>
<div class="management-toolbar">
    <select class="management-select" aria-label="Selecionar evento" disabled><option>Todos os eventos</option></select>
    <button class="management-button" type="button" disabled>＋ Adicionar parceiro</button>
</div>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Parceiro</th><th>Evento</th><th>Cota</th><th>Contrapartida</th><th>Status</th></tr></thead>
        <tbody>
            <tr><td data-label="Parceiro"><strong>Inova Tech</strong></td><td data-label="Evento">Semana de Tecnologia</td><td data-label="Cota">Ouro</td><td data-label="Contrapartida">Logo e estande</td><td data-label="Status"><span class="management-badge">Confirmado</span></td></tr>
            <tr><td data-label="Parceiro"><strong>Cooperativa Vale</strong></td><td data-label="Evento">Oficina de Dados</td><td data-label="Cota">Prata</td><td data-label="Contrapartida">Divulgação digital</td><td data-label="Status"><span class="management-badge management-badge--draft">Em negociação</span></td></tr>
            <tr><td data-label="Parceiro"><strong>DevCloud</strong></td><td data-label="Evento">Semana de Tecnologia</td><td data-label="Cota">Apoio</td><td data-label="Contrapartida">Materiais do evento</td><td data-label="Status"><span class="management-badge">Confirmado</span></td></tr>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
