<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

function renderManagementStart(array $usuario, string $titulo, string $descricao, string $paginaAtiva): void
{
    $perfil = (string) ($usuario['perfil'] ?? 'organizador');
    $isAdmin = $perfil === 'administrador';
    $inicio = $isAdmin ? '../administrador/index.php' : 'index.php';
    $linksOrganizador = [
        ['inicio', 'index.php', '▦', 'Painel'],
        ['eventos', 'eventos.php', '▣', 'Eventos'],
        ['presencas', 'presencas.php', '✓', 'Presenças'],
        ['mais', 'mais.php', '•••', 'Mais'],
    ];
    $linksAdmin = [
        ['inicio', 'index.php', '▦', 'Painel'],
        ['usuarios', 'usuarios.php', '♙', 'Usuários'],
    ];
    $links = $isAdmin ? $linksAdmin : $linksOrganizador;
    $tituloPerfil = $isAdmin ? 'Administração' : 'Organização';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#c0172d">
        <title><?= h($titulo) ?> | Eventeen</title>
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/gestao.css">
    </head>
    <body class="management-body">
        <div class="management-layout">
            <header class="management-topbar">
                <a class="management-brand" href="<?= h($inicio) ?>" aria-label="Eventeen - <?= h($tituloPerfil) ?>">
                    <span class="management-brand__mark">E</span>
                    <span>Eventeen</span>
                </a>
                <div class="management-topbar__right">
                    <span class="management-profile"><?= h($tituloPerfil) ?></span>
                    <span class="management-avatar" aria-label="<?= h(displayName($usuario)) ?>"><?= h(initials(displayName($usuario))) ?></span>
                    <a class="management-account" href="<?= h(applicationRootPrefix()) ?>configuracoes-conta.php">Minha conta</a>
                    <form method="post" action="<?= h(applicationRootPrefix()) ?>sair.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <button class="management-logout" type="submit">Sair</button>
                    </form>
                </div>
            </header>

            <nav class="management-sidebar" aria-label="Navegação da administração">
                <p class="management-sidebar__label"><?= h($tituloPerfil) ?></p>
                <?php foreach ($links as [$key, $url, $icon, $label]): ?>
                    <a class="management-nav<?= $paginaAtiva === $key ? ' management-nav--active' : '' ?>" href="<?= h($url) ?>">
                        <span class="management-nav__icon" aria-hidden="true"><?= h($icon) ?></span>
                        <span><?= h($label) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$isAdmin && $paginaAtiva !== 'mais'): ?>
                    <a class="management-nav management-nav--all" href="mais.php">Ver ferramentas de gestão <span aria-hidden="true">→</span></a>
                <?php endif; ?>
            </nav>

            <main class="management-main">
                <header class="management-page-heading">
                    <div>
                        <p class="management-eyebrow"><?= h($tituloPerfil) ?></p>
                        <h1><?= h($titulo) ?></h1>
                        <p><?= h($descricao) ?></p>
                    </div>
                    <span class="management-preview">Prévia de interface</span>
                </header>
                <div class="management-page-content">
                    <p class="management-notice" role="note">Dados ilustrativos para demonstração. As ações destas telas de gestão ainda não estão conectadas ao banco de dados.</p>
    <?php
}

function renderManagementEnd(array $usuario): void
{
    $isAdmin = ($usuario['perfil'] ?? '') === 'administrador';
    $links = $isAdmin
        ? [['index.php', '▦', 'Painel'], ['usuarios.php', '♙', 'Usuários']]
        : [['index.php', '▦', 'Painel'], ['eventos.php', '▣', 'Eventos'], ['presencas.php', '✓', 'Presenças'], ['mais.php', '•••', 'Mais']];
    $paginaAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $paginaAtiva = match ($paginaAtual) {
        'index.php' => 'Painel',
        'eventos.php', 'evento-form.php' => 'Eventos',
        'presencas.php' => 'Presenças',
        'mais.php', 'atividades.php', 'palestrantes.php', 'espacos.php', 'relatorios.php', 'parceiros.php', 'financeiro.php' => 'Mais',
        'usuarios.php' => 'Usuários',
        default => '',
    };
    ?>
                </div>
            </main>
            <nav class="management-mobile-nav" aria-label="Navegação principal">
                <?php foreach ($links as [$url, $icon, $label]): ?>
                    <a href="<?= h($url) ?>" class="management-mobile-nav__item<?= $paginaAtiva === $label ? ' management-mobile-nav__item--active' : '' ?>">
                        <span aria-hidden="true"><?= h($icon) ?></span>
                        <span><?= h($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </body>
    </html>
    <?php
}

function managementKpi(string $valor, string $rotulo, string $icone, string $variante = ''): void
{
    ?>
    <article class="management-kpi<?= $variante !== '' ? ' management-kpi--' . h($variante) : '' ?>">
        <span class="management-kpi__icon" aria-hidden="true"><?= h($icone) ?></span>
        <div><strong><?= h($valor) ?></strong><span><?= h($rotulo) ?></span></div>
    </article>
    <?php
}
