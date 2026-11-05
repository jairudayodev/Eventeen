<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Cadastro de evento', 'Descreva as informações principais e organize a publicação.', 'eventos');
?>
<section class="management-panel">
    <div class="management-panel__heading">
        <h2>Informações do evento</h2>
        <span class="management-badge management-badge--draft">Rascunho</span>
    </div>
    <div class="management-form-grid">
        <div class="management-field management-field--full">
            <label for="titulo-evento">Nome do evento</label>
            <input id="titulo-evento" class="management-input" type="text" placeholder="Ex.: Semana de Tecnologia 2026" disabled>
        </div>
        <div class="management-field">
            <label for="categoria-evento">Categoria</label>
            <select id="categoria-evento" class="management-select" disabled><option>Selecione uma categoria</option><option>Tecnologia</option><option>Palestra</option><option>Oficina</option></select>
        </div>
        <div class="management-field">
            <label for="local-evento">Espaço</label>
            <select id="local-evento" class="management-select" disabled><option>Selecione um espaço</option><option>Auditório</option><option>Laboratório 2</option></select>
        </div>
        <div class="management-field">
            <label for="inicio-evento">Início</label>
            <input id="inicio-evento" class="management-input" type="datetime-local" disabled>
        </div>
        <div class="management-field">
            <label for="fim-evento">Encerramento</label>
            <input id="fim-evento" class="management-input" type="datetime-local" disabled>
        </div>
        <div class="management-field">
            <label for="vagas-evento">Limite de participantes</label>
            <input id="vagas-evento" class="management-input" type="number" placeholder="Ex.: 120" disabled>
        </div>
        <div class="management-field">
            <label for="status-evento">Publicação</label>
            <select id="status-evento" class="management-select" disabled><option>Salvar como rascunho</option><option>Publicar evento</option></select>
        </div>
        <div class="management-field management-field--full">
            <label for="descricao-evento">Descrição</label>
            <textarea id="descricao-evento" class="management-textarea" placeholder="Conte aos participantes sobre o evento" disabled></textarea>
        </div>
    </div>
    <div class="management-actions management-actions--spaced">
        <button class="management-button" type="button" disabled>Salvar evento</button>
        <a class="management-button management-button--secondary" href="eventos.php">Voltar</a>
    </div>
</section>
<?php renderManagementEnd($usuario); ?>
