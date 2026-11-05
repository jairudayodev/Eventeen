<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Pagamentos e despesas', 'Registre e consulte movimentações feitas fora do Eventeen.', 'mais');
?>
<section class="management-kpis" aria-label="Resumo financeiro">
    <?php managementKpi('R$ 8.400', 'Apoios recebidos', '＋'); ?>
    <?php managementKpi('R$ 2.150', 'Despesas registradas', '−', 'red'); ?>
    <?php managementKpi('R$ 6.250', 'Saldo do evento', '＝'); ?>
    <?php managementKpi('3', 'Lançamentos pendentes', '◷', 'draft'); ?>
</section>
<div class="management-toolbar">
    <select class="management-select" aria-label="Selecionar evento" disabled><option>Semana de Tecnologia 2026</option></select>
    <button class="management-button" type="button" disabled>＋ Novo lançamento</button>
</div>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Descrição</th><th>Tipo</th><th>Data</th><th>Valor</th><th>Status</th></tr></thead>
        <tbody>
            <tr><td data-label="Descrição"><strong>Patrocínio Inova Tech</strong></td><td data-label="Tipo">Entrada</td><td data-label="Data">02 out 2026</td><td data-label="Valor">R$ 5.000,00</td><td data-label="Status"><span class="management-badge">Recebido</span></td></tr>
            <tr><td data-label="Descrição"><strong>Locação de equipamentos</strong></td><td data-label="Tipo">Despesa</td><td data-label="Data">05 out 2026</td><td data-label="Valor">R$ 1.250,00</td><td data-label="Status"><span class="management-badge">Pago</span></td></tr>
            <tr><td data-label="Descrição"><strong>Materiais de divulgação</strong></td><td data-label="Tipo">Despesa</td><td data-label="Data">08 out 2026</td><td data-label="Valor">R$ 900,00</td><td data-label="Status"><span class="management-badge management-badge--draft">A pagar</span></td></tr>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
