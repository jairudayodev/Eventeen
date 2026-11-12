<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (!empty($_SESSION['usuario'])) {
    header('Location: ' . profileHomePath((string) ($_SESSION['usuario']['perfil'] ?? 'participante')));
    exit;
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $senha = (string) ($_POST['senha'] ?? '');

    if (!$email || $senha === '') {
        $erro = 'Informe um e-mail válido e sua senha.';
    } else {
        $consulta = $pdo->prepare('SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email = :email LIMIT 1');
        $consulta->execute(['email' => $email]);
        $usuario = $consulta->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id' => (int) $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'perfil' => $usuario['perfil'],
            ];
            if (isset($_POST['manter-conectado'])) {
                setcookie(session_name(), session_id(), [
                    'expires' => time() + 30 * 24 * 60 * 60,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            header('Location: ' . profileHomePath((string) $usuario['perfil']));
            exit;
        }

        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Entrar | Eventeen</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="auth-page">
        <a class="marca" href="index.php" aria-label="Eventeen - página inicial">
            <img class="logo" src="img/logo-eventeen.png" alt="Eventeen">
        </a>

        <section class="auth-card" aria-labelledby="titulo">
            <header class="auth-header">
                <p class="eyebrow">Fatec Ourinhos</p>
                <h1 id="titulo">Bem-vindo ao Eventeen</h1>
                <p class="subtitulo">Eventos acadêmicos em um só lugar.</p>
            </header>

            <?php if ($erro !== null): ?>
                <p class="form-notice form-notice--error" role="alert"><?= h($erro) ?></p>
            <?php endif; ?>

            <form class="auth-form" action="index.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autocomplete="email" placeholder="seuemail@exemplo.com" required>
                </div>

                <div class="campo">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" autocomplete="current-password" placeholder="Digite sua senha" required>
                </div>

                <label class="checkbox-linha" for="manter-conectado">
                    <input type="checkbox" id="manter-conectado" name="manter-conectado">
                    <span>Manter conectado</span>
                </label>

                <button type="submit" class="botao">Entrar</button>
            </form>

            <p class="rodape">Ainda não tem conta? <a href="cadastro.php">Criar conta</a></p>
            <p class="rodape">Recebeu um certificado? <a href="validar-certificado.php">Validar certificado</a></p>
        </section>

        <footer class="assinatura">Fatec Ourinhos</footer>
    </main>
</body>
</html>