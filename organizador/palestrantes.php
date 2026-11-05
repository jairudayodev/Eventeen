<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Palestrantes', 'Organize os convidados e associe-os à programação.', 'mais');
?>
<div class="management-toolbar">
    <input class="management-search" type="search" placeholder="Buscar palestrantes" aria-label="Buscar palestrantes" disabled>
    <button class="management-button" type="button" disabled>＋ Adicionar palestrante</button>
</div>
<div class="management-feature-grid">
    <article class="management-panel">
        <span class="management-feature__icon" aria-hidden="true">MC</span>
        <h2>Marina Costa</h2>
        <p class="management-muted">Especialista em produtos digitais</p>
        <span class="management-badge">2 atividades</span>
    </article>
    <article class="management-panel">
        <span class="management-feature__icon" aria-hidden="true">RL</span>
        <h2>Rafael Lima</h2>
        <p class="management-muted">Pesquisador em ciência de dados</p>
        <span class="management-badge">1 atividade</span>
    </article>
    <article class="management-panel">
        <span class="management-feature__icon" aria-hidden="true">AS</span>
        <h2>Ana Souza</h2>
        <p class="management-muted">Consultora em inovação</p>
        <span class="management-badge management-badge--muted">Sem atividade</span>
    </article>
</div>
<?php renderManagementEnd($usuario); ?>
