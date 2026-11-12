<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/usuarios.php';

$usuario = requireRole('administrador');
$totais = contarUsuariosPorPerfil($pdo);
$eventos = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'publicado' AND data_fim >= NOW()), 0) AS abertos
     FROM eventos"
)->fetch();
$recentes = listarUsuarios($pdo, '', '', 5);

renderManagementStart($usuario, 'Painel administrativo', 'Visão geral da plataforma e atalhos para a administração.', 'inicio');
?>
<section class="management-kpis" aria-label="Resumo da plataforma">
    <?php managementKpi((string) $totais['total'], 'Usuários cadastrados', '♙'); ?>
    <?php managementKpi((string) $totais['organizador'], 'Organizadores', '▦'); ?>
    <?php managementKpi((string) $totais['administrador'], 'Administradores', '★'); ?>
    <?php managementKpi((int) $eventos['abertos'] . ' / ' . (int) $eventos['total'], 'Eventos abertos / total', '▣'); ?>
</section>
<div class="management-columns">
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Contas recentes</h2><a href="usuarios.php">Administrar usuários</a></div>
        <div class="management-rows">
            <?php if (!$recentes): ?>
                <div class="management-row"><div class="management-row__main"><strong>Nenhuma conta cadastrada</strong></div></div>
            <?php endif; ?>
            <?php foreach ($recentes as $conta): ?>
                <div class="management-row">
                    <div class="management-row__main">
                        <strong><?= h($conta['nome']) ?></strong>
                        <span><?= h($conta['email']) ?> · <?= h(rotuloPerfil($conta['perfil'])) ?></span>
                    </div>
                    <a class="management-button management-button--secondary" href="usuario-form.php?id=<?= (int) $conta['id'] ?>">Gerenciar</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Acesso rápido</h2><a href="../organizador/mais.php">Todas as ferramentas</a></div>
        <div class="management-feature-grid management-feature-grid--quick">
            <a class="management-feature" href="usuarios.php">
                <span class="management-feature__icon" aria-hidden="true">♙</span>
                <strong>Usuários e perfis</strong>
                <span>Conceda ou revogue os perfis de organizador e administrador.</span>
            </a>
            <a class="management-feature" href="../organizador/eventos.php">
                <span class="management-feature__icon" aria-hidden="true">▣</span>
                <strong>Todos os eventos</strong>
                <span>Edite, publique, cancele ou encerre qualquer evento.</span>
            </a>
            <a class="management-feature" href="../organizador/relatorios.php">
                <span class="management-feature__icon" aria-hidden="true">▥</span>
                <strong>Relatórios</strong>
                <span>Inscrições e presenças da plataforma.</span>
            </a>
            <a class="management-feature" href="../participante/inicio-participante.php">
                <span class="management-feature__icon" aria-hidden="true">◎</span>
                <strong>Área do participante</strong>
                <span>Veja o sistema como os participantes veem.</span>
            </a>
        </div>
    </section>
</div>
<?php renderManagementEnd($usuario); ?>
