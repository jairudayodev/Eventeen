<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Mais ferramentas', 'Acesse as ferramentas para organizar todos os detalhes dos eventos.', 'mais');
?>
<div class="management-feature-grid">
    <a class="management-feature" href="atividades.php"><span class="management-feature__icon">◷</span><strong>Atividades</strong><span>Programação, horários e agenda.</span></a>
    <a class="management-feature" href="palestrantes.php"><span class="management-feature__icon">♙</span><strong>Palestrantes</strong><span>Convidados e participação nas atividades.</span></a>
    <a class="management-feature" href="espacos.php"><span class="management-feature__icon">⌂</span><strong>Espaços</strong><span>Locais e capacidade disponível.</span></a>
    <a class="management-feature" href="relatorios.php"><span class="management-feature__icon">▥</span><strong>Relatórios</strong><span>Inscrições, presença e indicadores.</span></a>
    <a class="management-feature" href="parceiros.php"><span class="management-feature__icon">◇</span><strong>Parceiros</strong><span>Apoiadores e cotas de patrocínio.</span></a>
    <a class="management-feature" href="financeiro.php"><span class="management-feature__icon">R$</span><strong>Financeiro</strong><span>Pagamentos e despesas dos eventos.</span></a>
</div>
<?php renderManagementEnd($usuario); ?>
