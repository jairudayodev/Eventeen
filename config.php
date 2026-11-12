<?php
declare(strict_types=1);

/**
 * Configuração da aplicação.
 *
 * Os valores abaixo servem para o ambiente local (XAMPP). Em um servidor,
 * copie config.exemplo.php para config.local.php (arquivo fora do Git) e
 * informe ali o banco, o endereço público e o envio de e-mails.
 */
function appConfig(?string $chave = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = [
            'ambiente' => null,
            'url_base' => '',
            'forcar_https' => false,
            'pasta_dados' => __DIR__ . '/storage',
            'db' => ['host' => '127.0.0.1', 'nome' => 'eventeen', 'usuario' => 'root', 'senha' => ''],
            'email' => [
                'transporte' => 'arquivo',
                'remetente' => 'nao-responda@eventeen.local',
                'nome_remetente' => 'Eventeen',
                'smtp' => ['host' => '', 'porta' => 587, 'seguranca' => 'tls', 'usuario' => '', 'senha' => '', 'tempo_limite' => 15],
            ],
        ];
        $arquivoLocal = __DIR__ . '/config.local.php';
        if (is_file($arquivoLocal)) {
            $local = require $arquivoLocal;
            if (is_array($local)) {
                $config = array_replace_recursive($config, $local);
            }
        }
        foreach (['host' => 'EVENTEEN_DB_HOST', 'nome' => 'EVENTEEN_DB_NOME', 'usuario' => 'EVENTEEN_DB_USUARIO', 'senha' => 'EVENTEEN_DB_SENHA'] as $campo => $variavel) {
            $valor = getenv($variavel);
            if ($valor !== false) {
                $config['db'][$campo] = $valor;
            }
        }
        if (getenv('EVENTEEN_AMBIENTE') !== false) {
            $config['ambiente'] = getenv('EVENTEEN_AMBIENTE');
        }
        if (!in_array($config['ambiente'], ['desenvolvimento', 'producao'], true)) {
            // Sem configuração explícita, só se considera desenvolvimento quando
            // o acesso vem da própria máquina; qualquer outro acesso é tratado
            // como produção (erros ocultos).
            $local = PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
            $config['ambiente'] = $local ? 'desenvolvimento' : 'producao';
        }
    }

    return $chave === null ? $config : ($config[$chave] ?? null);
}

function appEmProducao(): bool
{
    return appConfig('ambiente') === 'producao';
}

function appPastaDados(string $subpasta): string
{
    $pasta = rtrim((string) appConfig('pasta_dados'), '/\\') . '/' . $subpasta;
    if (!is_dir($pasta)) {
        @mkdir($pasta, 0770, true);
    }

    return $pasta;
}

function requisicaoHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && appConfig('forcar_https'));
}

error_reporting(E_ALL);
ini_set('log_errors', '1');
if (appEmProducao()) {
    ini_set('display_errors', '0');
    ini_set('error_log', appPastaDados('logs') . '/php-erros.log');
    if (PHP_SAPI !== 'cli') {
        set_exception_handler(static function (Throwable $erro): void {
            error_log('Erro não tratado: ' . $erro);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Erro | Eventeen</title></head>'
                . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;color:#1f2937">'
                . '<h1 style="color:#c0172d">Algo deu errado</h1>'
                . '<p>Não foi possível concluir a operação agora. Tente novamente em alguns instantes.</p>'
                . '<p>Se o problema continuar, avise a organização do evento.</p>'
                . '<p><a href="' . h(applicationBasePath() . '/index.php') . '" style="color:#c0172d">Voltar ao início</a></p></body></html>';
        });
    }
} else {
    ini_set('display_errors', '1');
}

if (PHP_SAPI !== 'cli') {
    if (appEmProducao() && appConfig('forcar_https') && !requisicaoHttps()) {
        $hostAtual = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/^[A-Za-z0-9.\-]+(?::\d{1,5})?$/', $hostAtual)) {
            header('Location: https://' . $hostAtual . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    if (requisicaoHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => requisicaoHttps(),
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '2592000');
session_start();

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', appConfig('db')['host'], appConfig('db')['nome']),
    (string) appConfig('db')['usuario'],
    (string) appConfig('db')['senha'],
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

function requireLogin(): array
{
    global $pdo;

    $paginaLogin = applicationRootPrefix() . 'index.php';
    if (empty($_SESSION['usuario']['id'])) {
        header('Location: ' . $paginaLogin);
        exit;
    }

    // O perfil é relido a cada requisição para que concessões e revogações
    // feitas pelo administrador tenham efeito imediato (RNF04).
    $consulta = $pdo->prepare('SELECT id, nome, email, perfil, senha_hash FROM usuarios WHERE id = :id LIMIT 1');
    $consulta->execute(['id' => (int) $_SESSION['usuario']['id']]);
    $atual = $consulta->fetch();
    if (!$atual) {
        encerrarSessao();
        header('Location: ' . $paginaLogin);
        exit;
    }

    $agora = time();
    $ultimoAcesso = (int) ($_SESSION['ultimo_acesso'] ?? $agora);
    if ($agora - $ultimoAcesso > limiteInatividadeSessao((string) $atual['perfil'], !empty($_SESSION['manter_conectado']))) {
        encerrarSessao();
        header('Location: ' . $paginaLogin . '?sessao=expirada');
        exit;
    }

    // Se a senha foi trocada (em outro dispositivo ou pela recuperação de
    // senha), as demais sessões abertas deixam de valer.
    $assinatura = assinaturaSenha((string) $atual['senha_hash']);
    if (!isset($_SESSION['senha_assinatura'])) {
        $_SESSION['senha_assinatura'] = $assinatura;
    } elseif (!hash_equals((string) $_SESSION['senha_assinatura'], $assinatura)) {
        encerrarSessao();
        header('Location: ' . $paginaLogin . '?sessao=senha');
        exit;
    }

    $_SESSION['ultimo_acesso'] = $agora;
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
    // Em produção, links enviados por e-mail ou impressos em certificados usam
    // o endereço configurado, e não o cabeçalho Host da requisição.
    $urlConfigurada = trim((string) appConfig('url_base'));
    if ($urlConfigurada !== '') {
        return rtrim($urlConfigurada, '/');
    }

    $https = requisicaoHttps();
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

function formatPeriodo(string $inicio, string $fim): string
{
    $i = strtotime($inicio);
    $f = strtotime($fim);
    if (date('Y-m-d', $i) === date('Y-m-d', $f)) {
        return date('d/m/Y', $i) . ' · ' . date('H\hi', $i) . '–' . date('H\hi', $f);
    }

    return date('d/m/Y H\hi', $i) . ' até ' . date('d/m/Y H\hi', $f);
}

/**
 * Indica se a página atual pertence à área do participante (pasta participante/ ou atalhos da raiz).
 */
function paginaDaAreaParticipante(): bool
{
    $script = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));

    return basename(applicationScriptDirectory()) === 'participante'
        || in_array($script, [
            'inicio-participante.php', 'eventos.php', 'detalhes-evento.php', 'minhas-inscricoes.php',
            'cancelar-inscricao.php', 'historico-certificados.php', 'certificado.php',
        ], true);
}

function appHeader(array $usuario): void
{
    $perfil = (string) ($usuario['perfil'] ?? 'participante');
    $prefixo = applicationRootPrefix();
    $areaParticipante = paginaDaAreaParticipante();
    // O logo leva ao início da área exibida; fora dela, ao início do perfil do usuário.
    $paginaInicial = $prefixo . ($areaParticipante ? 'inicio-participante.php' : profileHomePath($perfil));
    $voltarGestao = $perfil !== 'participante'
        ? [$prefixo . profileHomePath($perfil), $perfil === 'administrador' ? 'Voltar à administração' : 'Voltar à organização']
        : null;
    ?>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="app-topbar">
        <a class="app-brand" href="<?= h($paginaInicial) ?>" aria-label="Eventeen - página inicial">
            <span class="app-brand__mark" aria-hidden="true">E</span>
            <span class="app-brand__name">Eventeen</span>
        </a>
        <div class="app-topbar__actions">
            <?php if ($voltarGestao !== null): ?>
                <a class="app-topbar__link app-topbar__link--destaque" href="<?= h($voltarGestao[0]) ?>" title="<?= h($voltarGestao[1]) ?>">
                    <span aria-hidden="true">←</span><span class="app-topbar__text"><?= h($voltarGestao[1]) ?></span>
                </a>
            <?php endif; ?>
            <span class="app-pill" title="<?= h(displayName($usuario)) ?>" aria-hidden="true"><?= h(initials(displayName($usuario))) ?></span>
            <a class="app-topbar__link" href="<?= h($prefixo) ?>configuracoes-conta.php" title="Minha conta">
                <span aria-hidden="true">⚙</span><span class="app-topbar__text">Minha conta</span>
            </a>
            <form class="app-logout-form" method="post" action="<?= h($prefixo) ?>sair.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <button class="app-topbar__link app-logout-button" type="submit" title="Sair da conta">
                    <span aria-hidden="true">↪</span><span class="app-topbar__text">Sair</span>
                </button>
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
        'historico' => ['historico-certificados.php', '🎓', 'Histórico'],
    ];
    ?>
    <nav class="app-bottom-nav" aria-label="Navegação principal">
        <?php foreach ($items as $key => [$url, $icon, $label]): ?>
            <a class="app-nav-item<?= $active === $key ? ' app-nav-item--active' : '' ?>" href="<?= h($url) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <span class="app-nav-item__icon" aria-hidden="true"><?= h($icon) ?></span>
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
    <p class="app-notice<?= $isError ? ' app-notice--error' : '' ?>" role="<?= $isError ? 'alert' : 'status' ?>"><?= h($message) ?></p>
    <?php
}
