<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Gerenciar eventos', 'Consulte a programação e acompanhe a situação de cada evento.', 'eventos');
?>
<div class="management-toolbar">
    <input class="management-search" type="search" placeholder="Buscar por nome do evento" aria-label="Buscar eventos" disabled>
    <div class="management-actions">
        <select class="management-select" aria-label="Filtrar por status" disabled><option>Todos os status</option></select>
        <a class="management-button" href="evento-form.php">＋ Novo evento</a>
    </div>
</div>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Evento</th><th>Data e local</th><th>Inscritos</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
            <tr>
                <td data-label="Evento"><strong>Semana de Tecnologia 2026</strong></td>
                <td data-label="Data e local">22 out · Auditório</td>
                <td data-label="Inscritos">94 / 120</td>
                <td data-label="Status"><span class="management-badge">Publicado</span></td>
                <td data-label="Ações"><a class="management-button management-button--secondary" href="evento-form.php">Editar</a></td>
            </tr>
            <tr>
                <td data-label="Evento"><strong>Oficina de Dados</strong></td>
                <td data-label="Data e local">24 out · Laboratório 2</td>
                <td data-label="Inscritos">24 / 30</td>
                <td data-label="Status"><span class="management-badge">Publicado</span></td>
                <td data-label="Ações"><a class="management-button management-button--secondary" href="evento-form.php">Editar</a></td>
            </tr>
            <tr>
                <td data-label="Evento"><strong>Conexões Inteligentes</strong></td>
                <td data-label="Data e local">12 nov · Bloco A</td>
                <td data-label="Inscritos">—</td>
                <td data-label="Status"><span class="management-badge management-badge--draft">Rascunho</span></td>
                <td data-label="Ações"><a class="management-button management-button--secondary" href="evento-form.php">Editar</a></td>
            </tr>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
