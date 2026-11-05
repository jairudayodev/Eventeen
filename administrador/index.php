<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('administrador');
renderManagementStart($usuario, 'Painel administrativo', 'Visão geral da plataforma e atalhos para a administração.', 'inicio');
?>
<section class="management-kpis" aria-label="Resumo da plataforma">
    <?php managementKpi('1.248', 'Usuários cadastrados', '♙'); ?>
    <?php managementKpi('18', 'Organizadores', '▦'); ?>
    <?php managementKpi('42', 'Eventos no sistema', '▣'); ?>
    <?php managementKpi('9', 'Contas pendentes', '◷', 'draft'); ?>
</section>
<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Contas recentes</h2><a href="usuarios.php">Administrar usuários</a></div>
        <div class="management-rows">
            <div class="management-row"><div class="management-row__main"><strong>Camila Rocha</strong><span>camila@example.com · Participante</span></div><span class="management-badge">Ativa</span></div>
            <div class="management-row"><div class="management-row__main"><strong>Diego Martins</strong><span>diego@example.com · Organizador</span></div><span class="management-badge">Ativa</span></div>
            <div class="management-row"><div class="management-row__main"><strong>Joana Ferreira</strong><span>joana@example.com · Participante</span></div><span class="management-badge management-badge--draft">Revisar</span></div>
        </div>
    </section>
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Ferramentas administrativas</h2></div>
        <a class="management-feature" href="usuarios.php">
            <span class="management-feature__icon">♙</span>
            <strong>Usuários e permissões</strong>
            <span>Consulte perfis de acesso e status das contas.</span>
        </a>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
