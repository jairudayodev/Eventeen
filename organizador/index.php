<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
renderManagementStart($usuario, 'Painel do organizador', 'Acompanhe seus eventos e as atividades da comunidade.', 'inicio');
?>
<section class="management-kpis" aria-label="Resumo do organizador">
    <?php managementKpi('8', 'Eventos publicados', '▣'); ?>
    <?php managementKpi('326', 'Inscrições ativas', '♙'); ?>
    <?php managementKpi('84%', 'Ocupação média', '◉'); ?>
    <?php managementKpi('12', 'Aguardando presença', '✓'); ?>
</section>

<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading">
            <h2>Eventos em destaque</h2>
            <a href="eventos.php">Ver todos</a>
        </div>
        <div class="management-rows">
            <div class="management-row">
                <div class="management-row__main"><strong>Semana de Tecnologia 2026</strong><span>22 out · Auditório</span></div>
                <span class="management-badge">Publicado</span>
            </div>
            <div class="management-row">
                <div class="management-row__main"><strong>Oficina de Dados</strong><span>24 out · Laboratório 2</span></div>
                <span class="management-badge">Publicado</span>
            </div>
            <div class="management-row">
                <div class="management-row__main"><strong>Conexões Inteligentes</strong><span>12 nov · Bloco A</span></div>
                <span class="management-badge management-badge--draft">Rascunho</span>
            </div>
        </div>
    </section>

    <section class="management-panel">
        <div class="management-panel__heading">
            <h2>Acesso rápido</h2>
            <a href="mais.php">Mais ferramentas</a>
        </div>
        <div class="management-feature-grid management-feature-grid--quick">
            <a class="management-feature" href="evento-form.php">
                <span class="management-feature__icon">＋</span>
                <strong>Criar evento</strong>
                <span>Prepare os dados do próximo evento.</span>
            </a>
            <a class="management-feature" href="presencas.php">
                <span class="management-feature__icon">✓</span>
                <strong>Registrar presença</strong>
                <span>Consulte a lista de participantes.</span>
            </a>
        </div>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
