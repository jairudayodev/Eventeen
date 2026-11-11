<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/usuarios.php';

$usuario = requireRole('administrador');
$termoRecebido = $_GET['q'] ?? '';
$termo = is_string($termoRecebido) ? trim($termoRecebido) : '';
$perfilRecebido = $_GET['perfil'] ?? '';
$perfilFiltro = is_string($perfilRecebido) && isset(PERFIS_USUARIO[$perfilRecebido]) ? $perfilRecebido : '';

$totais = contarUsuariosPorPerfil($pdo);
$usuarios = listarUsuarios($pdo, $termo, $perfilFiltro);
$mensagem = ($_GET['resultado'] ?? '') === 'salvo' ? 'Usuário atualizado com sucesso.' : null;

renderManagementStart($usuario, 'Administração de usuários', 'Consulte os usuários, atualize seus dados e gerencie os perfis de acesso.', 'usuarios');
?>
<section class="management-kpis" aria-label="Resumo de usuários">
    <?php managementKpi((string) $totais['total'], 'Total de usuários', '♙'); ?>
    <?php managementKpi((string) $totais['participante'], 'Participantes', '◎'); ?>
    <?php managementKpi((string) $totais['organizador'], 'Organizadores', '▦'); ?>
    <?php managementKpi((string) $totais['administrador'], 'Administradores', '★'); ?>
</section>
<?php if ($mensagem !== null): ?>
    <p class="management-notice" role="status"><?= h($mensagem) ?></p>
<?php endif; ?>
<form class="management-toolbar" method="get" action="usuarios.php">
    <input class="management-search" type="search" name="q" value="<?= h($termo) ?>" placeholder="Buscar por nome, e-mail ou matrícula" aria-label="Buscar usuários">
    <div class="management-actions">
        <select class="management-select" name="perfil" aria-label="Filtrar por perfil">
            <option value="">Todos os perfis</option>
            <?php foreach (PERFIS_USUARIO as $valor => $rotulo): ?>
                <option value="<?= h($valor) ?>"<?= $perfilFiltro === $valor ? ' selected' : '' ?>><?= h($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="management-button management-button--secondary" type="submit">Filtrar</button>
    </div>
</form>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Cadastro</th><th>Vínculos</th><th>Ações</th></tr></thead>
        <tbody>
            <?php if (!$usuarios): ?>
                <tr><td colspan="6">Nenhum usuário encontrado para os filtros informados.</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $conta):
                $classePerfil = ['participante' => 'management-badge--muted', 'administrador' => 'management-badge--red'][$conta['perfil']] ?? '';
            ?>
                <tr>
                    <td data-label="Nome"><strong><?= h($conta['nome']) ?></strong><?= $conta['matricula'] ? '<br><span class="management-muted">Matrícula ' . h($conta['matricula']) . '</span>' : '' ?></td>
                    <td data-label="E-mail"><?= h($conta['email']) ?></td>
                    <td data-label="Perfil"><span class="management-badge <?= h($classePerfil) ?>"><?= h(rotuloPerfil($conta['perfil'])) ?></span></td>
                    <td data-label="Cadastro"><?= h(date('d/m/Y', strtotime((string) $conta['criado_em']))) ?></td>
                    <td data-label="Vínculos"><?= (int) $conta['inscricoes_ativas'] ?> inscrição(ões) · <?= (int) $conta['eventos_organizados'] ?> evento(s) organizados</td>
                    <td data-label="Ações"><a class="management-button management-button--secondary" href="usuario-form.php?id=<?= (int) $conta['id'] ?>">Gerenciar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
