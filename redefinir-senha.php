<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/includes/autenticacao.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    try {
        redefinirSenhaComToken($pdo, $token, (string) ($_POST['nova_senha'] ?? ''), (string) ($_POST['confirmar_senha'] ?? ''));
        // Uma sessão aberta neste navegador não deve continuar com a senha antiga.
        encerrarSessao();
        header('Location: index.php?senha=redefinida');
        exit;
    } catch (DomainException $exception) {
        $erro = $exception->getMessage();
    }
}

$usuarioDoLink = usuarioDoTokenRedefinicao($pdo, $token);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <meta name="referrer" content="no-referrer">
    <title>Nova senha | Eventeen</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="auth-page">
        <a class="marca" href="index.php" aria-label="Eventeen - página inicial">
            <img class="logo" src="img/logo-eventeen.png" alt="Eventeen">
        </a>

        <section class="auth-card" aria-labelledby="titulo">
            <header class="auth-header">
                <p class="eyebrow">Recuperar acesso</p>
                <h1 id="titulo">Escolha uma nova senha</h1>
            </header>

            <?php if ($usuarioDoLink === null): ?>
                <p class="form-notice form-notice--error" role="alert">
                    Este link de redefinição é inválido, já foi usado ou expirou.
                </p>
                <p class="auth-texto">Os links valem por <?= REDEFINICAO_VALIDADE_MINUTOS ?> minutos e podem ser usados uma única vez.</p>
                <a class="botao" href="esqueci-senha.php">Pedir um novo link</a>
                <p class="rodape"><a href="index.php">Voltar para o login</a></p>
            <?php else: ?>
                <p class="auth-texto">Conta: <strong><?= h($usuarioDoLink['email']) ?></strong></p>

                <?php if ($erro !== null): ?>
                    <p class="form-notice form-notice--error" role="alert"><?= h($erro) ?></p>
                <?php endif; ?>

                <form class="auth-form" action="redefinir-senha.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <input type="text" name="username" value="<?= h($usuarioDoLink['email']) ?>" autocomplete="username" hidden>
                    <div class="campo">
                        <label for="nova-senha">Nova senha</label>
                        <input type="password" id="nova-senha" name="nova_senha" autocomplete="new-password" minlength="<?= SENHA_TAMANHO_MINIMO ?>" aria-describedby="senha-ajuda" required>
                        <small id="senha-ajuda" class="ajuda">Use pelo menos <?= SENHA_TAMANHO_MINIMO ?> caracteres.</small>
                    </div>
                    <div class="campo">
                        <label for="confirmar-senha">Confirmar nova senha</label>
                        <input type="password" id="confirmar-senha" name="confirmar_senha" autocomplete="new-password" minlength="<?= SENHA_TAMANHO_MINIMO ?>" required>
                    </div>
                    <button type="submit" class="botao">Salvar nova senha</button>
                </form>
            <?php endif; ?>
        </section>

        <footer class="assinatura">Fatec Ourinhos</footer>
    </main>
</body>
</html>
