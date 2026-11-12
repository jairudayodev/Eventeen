<?php
declare(strict_types=1);

$host = '127.0.0.1';
$db = 'eventeen';
$user = 'root';
$pass = '';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
ini_set('session.gc_maxlifetime', '2592000');
session_start();

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(400);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

function renderRequiredFieldsNote(): void
{
    ?>
    <p class="required-fields-note">Campos marcados com <span class="campo-obrigatorio" aria-hidden="true">*</span> são obrigatórios.</p>
    <?php
}

function requireLogin(): array
{
    global $pdo;

    if (empty($_SESSION['usuario']['id'])) {
        header('Location: ' . applicationRootPrefix() . 'index.php');
        exit;
    }

    // O perfil é relido a cada requisição para que concessões e revogações
    // feitas pelo administrador tenham efeito imediato (RNF04).
    $consulta = $pdo->prepare('SELECT id, nome, email, perfil FROM usuarios WHERE id = :id LIMIT 1');
    $consulta->execute(['id' => (int) $_SESSION['usuario']['id']]);
    $atual = $consulta->fetch();
    if (!$atual) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . applicationRootPrefix() . 'index.php');
        exit;
    }

    $_SESSION['usuario'] = [
        'id' => (int) $atual['id'],
        'nome' => $atual['nome'],
        'email' => $atual['email'],
        'perfil' => $atual['perfil'],
    ];

    return $_SESSION['usuario'];
}

function applicationBaseUrl(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(?::\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }

    return ($https ? 'https' : 'http') . '://' . $host . applicationBasePath();
}

function applicationRootPrefix(): string
{
    $scriptDirectory = applicationScriptDirectory();
    $scriptDirectory = rtrim($scriptDirectory, '/');
    $ultimaBarra = strrpos($scriptDirectory, '/');
    $pastaAtual = $ultimaBarra === false ? $scriptDirectory : substr($scriptDirectory, $ultimaBarra + 1);
    return in_array($pastaAtual, ['participante', 'organizador', 'administrador'], true) ? '../' : '';
}

function applicationBasePath(): string
{
    $scriptDirectory = applicationScriptDirectory();
    $normalizedDirectory = rtrim($scriptDirectory, '/');
    $ultimaBarra = strrpos($normalizedDirectory, '/');
    $pastaAtual = $ultimaBarra === false ? $normalizedDirectory : substr($normalizedDirectory, $ultimaBarra + 1);

    if (in_array($pastaAtual, ['participante', 'organizador', 'administrador'], true)) {
        $scriptDirectory = $ultimaBarra === false ? '' : substr($normalizedDirectory, 0, $ultimaBarra);
    }

    return $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');
}

function applicationScriptDirectory(): string
{
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $ultimaBarra = strrpos($scriptPath, '/');

    return $ultimaBarra === false ? '' : substr($scriptPath, 0, $ultimaBarra);
}

function profileHomePath(?string $perfil): string
{
    return match ($perfil) {
        'organizador' => 'organizador/index.php',
        'administrador' => 'administrador/index.php',
        default => 'inicio-participante.php',
    };
}

function requireRole(string ...$perfisPermitidos): array
{
    $usuario = requireLogin();
    $perfil = (string) ($usuario['perfil'] ?? 'participante');

    if ($perfil !== 'administrador' && !in_array($perfil, $perfisPermitidos, true)) {
        header('Location: ' . applicationRootPrefix() . profileHomePath($perfil));
        exit;
    }

    return $usuario;
}

function organizadorEscopoEventoSql(string $eventoId): string
{
    return "EXISTS (
        SELECT 1 FROM usuarios usuario_escopo
        WHERE usuario_escopo.id = :usuario_id
          AND (
              usuario_escopo.perfil = 'administrador'
              OR EXISTS (
                  SELECT 1 FROM evento_organizadores evento_escopo
                  WHERE evento_escopo.evento_id = {$eventoId}
                    AND evento_escopo.usuario_id = usuario_escopo.id
              )
          )
    )";
}

function displayName(array $usuario): string
{
    $nome = trim((string) ($usuario['nome'] ?? ''));
    return $nome !== '' ? $nome : 'Participante';
}

function initials(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $iniciais = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $iniciais !== '' ? $iniciais : 'EV';
}

function formatDateTime(string $dateTime): string
{
    return date('d/m/Y \\à\\s H\\hi', strtotime($dateTime));
}

function appHeader(array $usuario): void
{
    $paginaInicial = profileHomePath(isset($usuario['perfil']) ? (string) $usuario['perfil'] : null);
    $prefixo = applicationRootPrefix();
    ?>
    <header class="app-topbar">
        <a class="app-brand" href="<?= h($paginaInicial) ?>" aria-label="Página inicial Eventeen">
            <span class="app-brand__mark">E</span>
            <span class="app-brand__name">Eventeen</span>
        </a>
        <div class="app-topbar__actions">
            <span class="app-pill"><?= h(initials(displayName($usuario))) ?></span>
            <a class="app-menu" href="<?= h($prefixo) ?>configuracoes-conta.php" aria-label="Configurações da conta" title="Configurações">⚙</a>
            <form class="app-logout-form" method="post" action="<?= h($prefixo) ?>sair.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <button class="app-menu app-logout-button" type="submit" aria-label="Sair da conta" title="Sair">↪</button>
            </form>
        </div>
    </header>
    <?php
}

function appNavigation(string $active): void
{
    $items = [
        'inicio' => ['inicio-participante.php', '⌂', 'Início'],
        'eventos' => ['eventos.php', '🗓️', 'Eventos'],
        'inscricoes' => ['minhas-inscricoes.php', '🧾', 'Inscrições'],
        'historico' => ['historico-certificados.php', '⏱️', 'Histórico'],
    ];
    ?>
    <nav class="app-bottom-nav" aria-label="Navegação principal">
        <?php foreach ($items as $key => [$url, $icon, $label]): ?>
            <a class="app-nav-item<?= $active === $key ? ' app-nav-item--active' : '' ?>" href="<?= h($url) ?>">
                <span class="app-nav-item__icon"><?= h($icon) ?></span>
                <span><?= h($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function renderNotice(?string $message, bool $isError = false): void
{
    if ($message === null || $message === '') {
        return;
    }
    ?>
    <p class="app-notice<?= $isError ? ' app-notice--error' : '' ?>" role="status"><?= h($message) ?></p>
    <?php
}
