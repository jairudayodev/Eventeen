<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = requireLogin();
$usuarioId = (int) $usuario['id'];

$consulta = $pdo->prepare(
    'SELECT nome, email, matricula, senha_hash, perfil
     FROM usuarios WHERE id = :id LIMIT 1'
);
$consulta->execute(['id' => $usuarioId]);
$dadosConta = $consulta->fetch();

if (!$dadosConta) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['usuario']['nome'] = $dadosConta['nome'];
$_SESSION['usuario']['email'] = $dadosConta['email'];
$_SESSION['usuario']['perfil'] = $dadosConta['perfil'];
$usuario = array_merge($usuario, [
    'nome' => $dadosConta['nome'],
    'email' => $dadosConta['email'],
    'perfil' => $dadosConta['perfil'],
]);

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'perfil') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = filter_var(normalizarEmailAcesso(trim((string) ($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
        $matricula = trim((string) ($_POST['matricula'] ?? ''));

        if ($nome === '' || mb_strlen($nome, 'UTF-8') > 150 || !$email || mb_strlen($email, 'UTF-8') > 190) {
            $erro = 'Informe um nome e um e-mail válidos.';
        } elseif (mb_strlen($matricula, 'UTF-8') > 40) {
            $erro = 'A matrícula deve ter no máximo 40 caracteres.';
        } else {
            try {
                $atualizar = $pdo->prepare(
                    'UPDATE usuarios
                     SET nome = :nome, email = :email, matricula = :matricula
                     WHERE id = :id'
                );
                $atualizar->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'matricula' => $matricula !== '' ? $matricula : null,
                    'id' => $usuarioId,
                ]);
                $_SESSION['usuario']['nome'] = $nome;
                $_SESSION['usuario']['email'] = $email;
                header('Location: configuracoes-conta.php?salvo=perfil');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $erro = 'Este e-mail ou matrícula já está em uso por outra conta.';
            }
        }
    } elseif ($acao === 'senha') {
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

        if (!password_verify($senhaAtual, $dadosConta['senha_hash'])) {
            $erro = 'A senha atual está incorreta.';
        } elseif (strlen($novaSenha) < 8) {
            $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
        } elseif ($novaSenha !== $confirmacao) {
            $erro = 'A confirmação não corresponde à nova senha.';
        } else {
            $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $atualizar = $pdo->prepare('UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id');
            $atualizar->execute([
                'senha_hash' => $novoHash,
                'id' => $usuarioId,
            ]);
            // Esta sessão continua válida; as abertas em outros dispositivos são encerradas.
            session_regenerate_id(true);
            $_SESSION['senha_assinatura'] = assinaturaSenha($novoHash);
            header('Location: configuracoes-conta.php?salvo=senha');
            exit;
        }
    } else {
        http_response_code(400);
        exit('Ação inválida.');
    }

    $dadosConta['nome'] = $nome ?? $dadosConta['nome'];
    $dadosConta['email'] = is_string($email ?? null) ? $email : $dadosConta['email'];
    $dadosConta['matricula'] = $matricula ?? $dadosConta['matricula'];
}

$mensagensSalvas = [
    'perfil' => 'Dados da conta atualizados com sucesso.',
    'senha' => 'Senha alterada com sucesso.',
];
$mensagem = $mensagensSalvas[(string) ($_GET['salvo'] ?? '')] ?? null;
$prefixo = applicationRootPrefix();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Configurações da conta | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content" id="conteudo">
            <header class="app-header">
                <h1>Configurações da conta</h1>
                <p class="app-header__subtitle">Atualize seus dados pessoais e sua senha.</p>
            </header>
            <?php renderNotice($mensagem); ?>
            <?php if ($erro !== null): ?>
                <p class="app-notice app-notice--error" role="alert"><?= h($erro) ?></p>
            <?php endif; ?>

            <section class="account-settings-card" aria-labelledby="dados-pessoais">
                <header class="account-settings-card__header">
                    <h2 id="dados-pessoais">Dados pessoais</h2>
                    <p>Essas informações identificam sua conta no Eventeen.</p>
                </header>
                <form class="auth-form" method="post" action="<?= h($prefixo) ?>configuracoes-conta.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="acao" value="perfil">
                    <?php renderRequiredFieldsNote(); ?>
                    <div class="campo">
                        <label for="nome">Nome completo <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="text" id="nome" name="nome" autocomplete="name" maxlength="150" value="<?= h($dadosConta['nome']) ?>" required>
                    </div>
                    <div class="campo">
                        <label for="email">E-mail <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" autocomplete="email" maxlength="190" value="<?= h($dadosConta['email']) ?>" required>
                    </div>
                    <div class="campo">
                        <label for="matricula">Matrícula <span class="opcional">(opcional)</span></label>
                        <input type="text" id="matricula" name="matricula" maxlength="40" value="<?= h($dadosConta['matricula']) ?>">
                    </div>
                    <div class="account-settings-card__footer">
                        <span class="account-role">Perfil: <?= h(ucfirst($dadosConta['perfil'])) ?></span>
                        <button class="botao" type="submit">Salvar alterações</button>
                    </div>
                </form>
            </section>

            <section class="account-settings-card" aria-labelledby="alterar-senha">
                <header class="account-settings-card__header">
                    <h2 id="alterar-senha">Alterar senha</h2>
                    <p>Confirme sua senha atual antes de escolher uma nova.</p>
                </header>
                <form class="auth-form" method="post" action="<?= h($prefixo) ?>configuracoes-conta.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="acao" value="senha">
                    <?php renderRequiredFieldsNote(); ?>
                    <div class="campo">
                        <label for="senha-atual">Senha atual <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="password" id="senha-atual" name="senha_atual" autocomplete="current-password" required>
                    </div>
                    <div class="campo">
                        <label for="nova-senha">Nova senha <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="password" id="nova-senha" name="nova_senha" autocomplete="new-password" minlength="8" aria-describedby="senha-ajuda" required>
                        <small id="senha-ajuda" class="ajuda">Use pelo menos 8 caracteres.</small>
                    </div>
                    <div class="campo">
                        <label for="confirmar-senha">Confirmar nova senha <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="password" id="confirmar-senha" name="confirmar_senha" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="account-settings-card__footer">
                        <a class="account-back-link" href="<?= h($prefixo . profileHomePath((string) $dadosConta['perfil'])) ?>">Voltar ao início</a>
                        <button class="botao" type="submit">Alterar senha</button>
                    </div>
                </form>
            </section>
        </main>
        <?php if ($dadosConta['perfil'] === 'participante'): ?>
            <?php appNavigation(''); ?>
        <?php endif; ?>
    </div>
</body>
</html>
