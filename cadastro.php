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
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $matricula = trim((string) ($_POST['matricula'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar-senha'] ?? '');

    if ($nome === '' || strlen($nome) > 150 || !$email || strlen($email) > 190) {
        $erro = 'Informe um nome e um e-mail válidos.';
    } elseif (strlen($matricula) > 40) {
        $erro = 'A matrícula deve ter no máximo 40 caracteres.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmacao) {
        $erro = 'As senhas não coincidem.';
    } elseif (!isset($_POST['termos'])) {
        $erro = 'É necessário concordar com os termos de uso.';
    } else {
        try {
            $inserir = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, matricula, senha_hash)
                 VALUES (:nome, :email, :matricula, :senha_hash)'
            );
            $inserir->execute([
                'nome' => $nome,
                'email' => $email,
                'matricula' => $matricula !== '' ? $matricula : null,
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id' => (int) $pdo->lastInsertId(),
                'nome' => $nome,
                'email' => $email,
                'perfil' => 'participante',
            ];
            header('Location: inicio-participante.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $erro = 'Este e-mail ou matrícula já está cadastrado.';
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
    <title>Criar conta | Eventeen</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="auth-page auth-page--cadastro">
        <a class="marca" href="index.php" aria-label="Eventeen - página inicial">
            <img class="logo" src="img/logo-eventeen.png" alt="Eventeen">
        </a>

        <section class="auth-card auth-card--cadastro" aria-labelledby="titulo">
            <header class="auth-header">
                <p class="eyebrow">Faça parte da nossa comunidade</p>
                <h1 id="titulo">Criar conta</h1>
                <p class="subtitulo">Seu acesso aos eventos acadêmicos.</p>
            </header>

            <?php if ($erro !== null): ?>
                <p class="form-notice form-notice--error" role="alert"><?= h($erro) ?></p>
            <?php endif; ?>

            <form class="auth-form" action="cadastro.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <div class="campo">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" autocomplete="name" placeholder="Digite seu nome completo" required>
                </div>

                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autocomplete="email" placeholder="seuemail@exemplo.com" required>
                </div>

                <div class="campo">
                    <label for="matricula">Matrícula <span class="opcional">(opcional)</span></label>
                    <input type="text" id="matricula" name="matricula" autocomplete="off" placeholder="Digite sua matrícula">
                </div>

                <div class="campo">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" placeholder="Crie uma senha" aria-describedby="senha-ajuda" required>
                    <small id="senha-ajuda" class="ajuda">Use pelo menos 8 caracteres.</small>
                </div>

                <div class="campo">
                    <label for="confirmar-senha">Confirmar senha</label>
                    <input type="password" id="confirmar-senha" name="confirmar-senha" autocomplete="new-password" minlength="8" placeholder="Digite sua senha novamente" required>
                </div>

                <label class="checkbox-linha checkbox-linha--termos" for="termos">
                    <input type="checkbox" id="termos" name="termos" required>
                    <span>Concordo com os <strong>termos de uso</strong></span>
                </label>

                <button type="submit" class="botao">Criar conta</button>
            </form>

            <p class="rodape">Já tem uma conta? <a href="index.php">Entrar</a></p>
        </section>
    </main>

    <script>
        const senha = document.getElementById('senha');
        const confirmarSenha = document.getElementById('confirmar-senha');

        function validarSenhas() {
            confirmarSenha.setCustomValidity(
                confirmarSenha.value && confirmarSenha.value !== senha.value
                    ? 'As senhas não coincidem.'
                    : ''
            );
        }

        senha.addEventListener('input', validarSenhas);
        confirmarSenha.addEventListener('input', validarSenhas);
    </script>
</body>
</html>
