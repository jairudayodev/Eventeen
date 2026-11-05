<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('administrador');
renderManagementStart($usuario, 'Administração de usuários', 'Consulte os usuários e seus perfis de acesso à plataforma.', 'usuarios');
?>
<section class="management-kpis" aria-label="Resumo de usuários">
    <?php managementKpi('1.248', 'Total de usuários', '♙'); ?>
    <?php managementKpi('1.221', 'Participantes', '◎'); ?>
    <?php managementKpi('18', 'Organizadores', '▦'); ?>
    <?php managementKpi('9', 'A revisar', '◷', 'draft'); ?>
</section>
<div class="management-toolbar">
    <input class="management-search" type="search" placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuários" disabled>
    <div class="management-actions">
        <select class="management-select" aria-label="Filtrar por perfil" disabled><option>Todos os perfis</option></select>
        <button class="management-button" type="button" disabled>＋ Novo usuário</button>
    </div>
</div>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Cadastro</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
            <tr><td data-label="Nome"><strong>Camila Rocha</strong></td><td data-label="E-mail">camila@example.com</td><td data-label="Perfil">Participante</td><td data-label="Cadastro">20 out 2026</td><td data-label="Status"><span class="management-badge">Ativa</span></td><td data-label="Ações"><button class="management-button management-button--secondary" type="button" disabled>Gerenciar</button></td></tr>
            <tr><td data-label="Nome"><strong>Diego Martins</strong></td><td data-label="E-mail">diego@example.com</td><td data-label="Perfil">Organizador</td><td data-label="Cadastro">18 out 2026</td><td data-label="Status"><span class="management-badge">Ativa</span></td><td data-label="Ações"><button class="management-button management-button--secondary" type="button" disabled>Gerenciar</button></td></tr>
            <tr><td data-label="Nome"><strong>Joana Ferreira</strong></td><td data-label="E-mail">joana@example.com</td><td data-label="Perfil">Participante</td><td data-label="Cadastro">17 out 2026</td><td data-label="Status"><span class="management-badge management-badge--draft">A revisar</span></td><td data-label="Ações"><button class="management-button management-button--secondary" type="button" disabled>Gerenciar</button></td></tr>
            <tr><td data-label="Nome"><strong>Rafael Lima</strong></td><td data-label="E-mail">rafael@example.com</td><td data-label="Perfil">Administrador</td><td data-label="Cadastro">12 out 2026</td><td data-label="Status"><span class="management-badge">Ativa</span></td><td data-label="Ações"><button class="management-button management-button--secondary" type="button" disabled>Gerenciar</button></td></tr>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
