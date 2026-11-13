<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

/**
 * Identifica a página de gestão atual (pasta/arquivo) para destacar o item do menu.
 */
function managementPaginaAtual(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pasta = basename(dirname($script));
    $arquivo = basename($script, '.php');

    return match ($pasta . '/' . $arquivo) {
        'administrador/index' => 'admin',
        'administrador/usuarios', 'administrador/usuario-form' => 'usuarios',
        'organizador/index' => 'inicio',
        'organizador/eventos', 'organizador/evento-form' => 'eventos',
        default => $arquivo,
    };
}

/**
 * Menu das áreas de gestão, agrupado por assunto. Os caminhos partem da pasta da página
 * (organizador/ ou administrador/), por isso sempre sobem um nível.
 */
function managementMenu(bool $isAdmin): array
{
    $grupos = [];
    if ($isAdmin) {
        $grupos['Administração'] = [
            ['admin', '../administrador/index.php', '★', 'Painel administrativo'],
            ['usuarios', '../administrador/usuarios.php', '♙', 'Usuários e perfis'],
        ];
    }
    $grupos['Eventos'] = [
        ['inicio', '../organizador/index.php', '▦', $isAdmin ? 'Painel de eventos' : 'Painel'],
        ['eventos', '../organizador/eventos.php', '▣', 'Eventos'],
        ['presencas', '../organizador/presencas.php', '✓', 'Inscrições e presenças'],
        ['relatorios', '../organizador/relatorios.php', '▥', 'Relatórios'],
    ];
    $grupos['Programação'] = [
        ['atividades', '../organizador/atividades.php', '◷', 'Atividades'],
        ['palestrantes', '../organizador/palestrantes.php', '♟', 'Palestrantes'],
        ['espacos', '../organizador/espacos.php', '⌂', 'Espaços'],
    ];
    $grupos['Financeiro'] = [
        ['parceiros', '../organizador/parceiros.php', '◇', 'Parceiros e patrocínios'],
        ['financeiro', '../organizador/financeiro.php', 'R$', 'Pagamentos e despesas'],
    ];
    if ($isAdmin) {
        $grupos['Outras áreas'] = [
            ['participante', '../participante/inicio-participante.php', '◎', 'Área do participante'],
        ];
    }

    return $grupos;
}

function renderManagementStart(array $usuario, string $titulo, string $descricao, string $paginaAtiva = '', bool $previsualizacao = false): void
{
    $perfil = (string) ($usuario['perfil'] ?? 'organizador');
    $isAdmin = $perfil === 'administrador';
    $inicio = $isAdmin ? '../administrador/index.php' : '../organizador/index.php';
    $tituloPerfil = $isAdmin ? 'Administração' : 'Organização';
    $atual = managementPaginaAtual();
    $prefixo = applicationRootPrefix();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#c0172d">
        <title><?= h($titulo) ?> | Eventeen: do convite ao certificado</title>
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/gestao.css">
    </head>
    <body class="management-body">
        <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
        <div class="management-layout">
            <header class="management-topbar">
                <a class="management-brand" href="<?= h($inicio) ?>" aria-label="Eventeen - início da <?= h(mb_strtolower($tituloPerfil, 'UTF-8')) ?>">
                    <span class="management-brand__mark" aria-hidden="true">E</span>
                    <span>Eventeen</span>
                </a>
                <div class="management-topbar__right">
                    <span class="management-profile"><?= h($tituloPerfil) ?></span>
                    <span class="management-avatar" title="<?= h(displayName($usuario)) ?>" aria-hidden="true"><?= h(initials(displayName($usuario))) ?></span>
                    <a class="management-account" href="<?= h($prefixo) ?>configuracoes-conta.php" title="Minha conta">
                        <span aria-hidden="true">⚙</span><span class="management-topbar__text">Minha conta</span>
                    </a>
                    <form method="post" action="<?= h($prefixo) ?>sair.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <button class="management-logout" type="submit" title="Sair da conta">
                            <span aria-hidden="true">↪</span><span class="management-topbar__text">Sair</span>
                        </button>
                    </form>
                </div>
            </header>

            <nav class="management-sidebar" aria-label="Menu da <?= h(mb_strtolower($tituloPerfil, 'UTF-8')) ?>">
                <?php foreach (managementMenu($isAdmin) as $grupo => $itens): ?>
                    <p class="management-sidebar__label"><?= h($grupo) ?></p>
                    <?php foreach ($itens as [$chave, $url, $icone, $rotulo]): ?>
                        <a class="management-nav<?= $atual === $chave ? ' management-nav--active' : '' ?>" href="<?= h($url) ?>"<?= $atual === $chave ? ' aria-current="page"' : '' ?>>
                            <span class="management-nav__icon" aria-hidden="true"><?= h($icone) ?></span>
                            <span><?= h($rotulo) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>

            <main class="management-main" id="conteudo">
                <header class="management-page-heading">
                    <div>
                        <p class="management-eyebrow"><?= h($tituloPerfil) ?></p>
                        <h1><?= h($titulo) ?></h1>
                        <p><?= h($descricao) ?></p>
                    </div>
                    <?php if ($previsualizacao): ?><span class="management-preview">Prévia de interface</span><?php endif; ?>
                </header>
                <div class="management-page-content">
    <?php
}

function renderManagementEnd(array $usuario): void
{
    $isAdmin = ($usuario['perfil'] ?? '') === 'administrador';
    $atual = managementPaginaAtual();
    $links = $isAdmin
        ? [
            ['admin', '../administrador/index.php', '★', 'Painel'],
            ['eventos', '../organizador/eventos.php', '▣', 'Eventos'],
            ['usuarios', '../administrador/usuarios.php', '♙', 'Usuários'],
            ['mais', '../organizador/mais.php', '•••', 'Mais'],
        ]
        : [
            ['inicio', '../organizador/index.php', '▦', 'Painel'],
            ['eventos', '../organizador/eventos.php', '▣', 'Eventos'],
            ['presencas', '../organizador/presencas.php', '✓', 'Presenças'],
            ['mais', '../organizador/mais.php', '•••', 'Mais'],
        ];
    $chaves = array_column($links, 0);
    if (!in_array($atual, $chaves, true)) {
        $atual = 'mais';
    }
    ?>
                </div>
            </main>
            <nav class="management-mobile-nav" aria-label="Navegação principal">
                <?php foreach ($links as [$chave, $url, $icone, $rotulo]): ?>
                    <a href="<?= h($url) ?>" class="management-mobile-nav__item<?= $atual === $chave ? ' management-mobile-nav__item--active' : '' ?>"<?= $atual === $chave ? ' aria-current="page"' : '' ?>>
                        <span aria-hidden="true"><?= h($icone) ?></span>
                        <span><?= h($rotulo) ?></span>
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

/**
 * Rótulo de evento para listas de seleção: título, data e situação quando não publicado.
 */
function managementRotuloEvento(array $evento): string
{
    $rotulo = (string) $evento['titulo'];
    if (!empty($evento['data_inicio'])) {
        $rotulo .= ' · ' . date('d/m/Y', strtotime((string) $evento['data_inicio']));
    }
    $situacoes = ['rascunho' => 'rascunho', 'cancelado' => 'cancelado', 'encerrado' => 'encerrado'];
    if (isset($evento['status'], $situacoes[$evento['status']])) {
        $rotulo .= ' (' . $situacoes[$evento['status']] . ')';
    }

    return $rotulo;
}

/**
 * Campos do seletor de evento usado nas telas de programação, presenças e finanças.
 */
function managementEventPicker(array $eventos, ?int $selecionado, string $rotuloBotao = 'Abrir'): void
{
    ?>
    <label class="management-event-picker__label" for="seletor-evento">Evento</label>
    <select id="seletor-evento" class="management-select management-event-picker__select" name="evento_id">
        <?php foreach ($eventos as $evento): ?>
            <option value="<?= (int) $evento['id'] ?>"<?= (int) $evento['id'] === $selecionado ? ' selected' : '' ?>><?= h(managementRotuloEvento($evento)) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="management-button management-button--secondary" type="submit"><?= h($rotuloBotao) ?></button>
    <?php
}
