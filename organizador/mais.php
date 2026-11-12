<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');
$isAdmin = $usuario['perfil'] === 'administrador';
$descricoes = [
    'admin' => 'Totais da plataforma e contas recentes.',
    'usuarios' => 'Contas, perfis de acesso e permissões.',
    'inicio' => 'Resumo dos eventos, inscrições e presenças.',
    'eventos' => 'Cadastro, publicação, cancelamento e encerramento.',
    'presencas' => 'Inscritos por evento e presença por atividade.',
    'relatorios' => 'Inscrições e presenças por evento e período.',
    'atividades' => 'Programação, horários e agenda.',
    'palestrantes' => 'Convidados e participação nas atividades.',
    'espacos' => 'Locais e capacidade disponível.',
    'parceiros' => 'Parceiros, cotas e tipos de apoio.',
    'financeiro' => 'Pagamentos de inscrição, despesas e saldo.',
    'participante' => 'Ver o sistema como participante.',
];
renderManagementStart($usuario, 'Ferramentas', 'Todas as ferramentas de gestão, organizadas por assunto.', 'mais');
?>
<?php foreach (managementMenu($isAdmin) as $grupo => $itens): ?>
    <section class="management-tool-group" aria-labelledby="grupo-<?= h(md5($grupo)) ?>">
        <h2 class="management-tool-group__title" id="grupo-<?= h(md5($grupo)) ?>"><?= h($grupo) ?></h2>
        <div class="management-feature-grid">
            <?php foreach ($itens as [$chave, $url, $icone, $rotulo]): ?>
                <a class="management-feature" href="<?= h($url) ?>">
                    <span class="management-feature__icon" aria-hidden="true"><?= h($icone) ?></span>
                    <strong><?= h($rotulo) ?></strong>
                    <span><?= h($descricoes[$chave] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php renderManagementEnd($usuario); ?>
