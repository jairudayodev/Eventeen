<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/includes/autenticacao.php';
require __DIR__ . '/includes/email.php';

$erro = null;
$enviado = false;
$emailInformado = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $emailInformado = trim((string) ($_POST['email'] ?? ''));
    $email = filter_var(normalizarEmailAcesso($emailInformado), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            $pedido = solicitarRedefinicaoSenha($pdo, $email, ipCliente());
            if ($pedido !== null) {
                $link = applicationBaseUrl() . '/redefinir-senha.php?token=' . $pedido['token'];
                enviarEmail((string) $pedido['usuario']['email'], 'Redefinição de senha do Eventeen', implode("\n", [
                    'Olá, ' . $pedido['usuario']['nome'] . '.',
                    '',
                    'Recebemos um pedido para redefinir a senha da sua conta no Eventeen.',
                    'Para escolher uma nova senha, acesse o link abaixo em até ' . REDEFINICAO_VALIDADE_MINUTOS . ' minutos:',
                    '',
                    $link,
                    '',
                    'Se você não fez esse pedido, ignore esta mensagem: sua senha continua a mesma.',
                    '',
                    'Equipe Eventeen',
                ]));
            }
            // A resposta é a mesma havendo ou não conta com esse e-mail.
            $enviado = true;
        } catch (DomainException $exception) {
            $erro = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Recuperar senha | Eventeen</title>
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
                <h1 id="titulo">Esqueceu sua senha?</h1>
            </header>

            <?php if ($enviado): ?>
                <p class="form-notice form-notice--success" role="status">
                    Se houver uma conta com o e-mail <strong><?= h($emailInformado) ?></strong>, enviamos um link para criar uma nova senha.
                    Ele vale por <?= REDEFINICAO_VALIDADE_MINUTOS ?> minutos. Confira também a caixa de spam.
                </p>
                <p class="rodape"><a href="index.php">Voltar para o login</a></p>
            <?php else: ?>
                <p class="auth-texto">Informe o e-mail da sua conta. Enviaremos um link para você escolher uma nova senha.</p>

                <?php if ($erro !== null): ?>
                    <p class="form-notice form-notice--error" role="alert"><?= h($erro) ?></p>
                <?php endif; ?>

                <form class="auth-form" action="esqueci-senha.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" autocomplete="email" placeholder="seuemail@exemplo.com" value="<?= h($emailInformado) ?>" required>
                    </div>
                    <button type="submit" class="botao">Enviar link</button>
                </form>

                <p class="rodape">Lembrou a senha? <a href="index.php">Entrar</a></p>
            <?php endif; ?>
        </section>

        <footer class="assinatura">Fatec Ourinhos</footer>
    </main>
</body>
</html>
