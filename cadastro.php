<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/includes/autenticacao.php';

if (!empty($_SESSION['usuario'])) {
    header('Location: ' . profileHomePath((string) ($_SESSION['usuario']['perfil'] ?? 'participante')));
    exit;
}

$erro = null;
$perfil = 'participante';
$codigoOrganizador = (string) ($_POST['codigo_organizador'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = filter_var(normalizarEmailAcesso(trim((string) ($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar-senha'] ?? '');
    $perfil = (string) ($_POST['perfil'] ?? '');
    $matricula = $perfil === 'participante' ? trim((string) ($_POST['matricula'] ?? '')) : '';

    if (!in_array($perfil, ['participante', 'organizador'], true)) {
        $erro = 'Selecione se deseja se cadastrar como participante ou organizador.';
    } elseif ($nome === '' || strlen($nome) > 150 || !$email || strlen($email) > 190) {
        $erro = 'Informe um nome e um e-mail válidos.';
    } elseif (strlen($matricula) > 40) {
        $erro = 'A matrícula deve ter no máximo 40 caracteres.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmacao) {
        $erro = 'As senhas não coincidem.';
    } elseif (!isset($_POST['termos'])) {
        $erro = 'É necessário concordar com os termos de uso.';
    } elseif ($perfil === 'organizador' && !codigoOrganizadorValido($codigoOrganizador)) {
        $erro = 'O código de organizador é inválido.';
    } else {
        try {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $inserir = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil)
                 VALUES (:nome, :email, :matricula, :senha_hash, :perfil)'
            );
            $inserir->execute([
                'nome' => $nome,
                'email' => $email,
                'matricula' => $matricula !== '' ? $matricula : null,
                'senha_hash' => $senhaHash,
                'perfil' => $perfil,
            ]);

            iniciarSessaoUsuario([
                'id' => (int) $pdo->lastInsertId(),
                'nome' => $nome,
                'email' => $email,
                'perfil' => $perfil,
                'senha_hash' => $senhaHash,
            ]);
            header('Location: ' . profileHomePath($perfil));
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
                <p class="ajuda ajuda--obrigatorios">Campos marcados com <strong class="campo-obrigatorio">*</strong> são obrigatórios.</p>
            </header>

            <?php if ($erro !== null): ?>
                <p class="form-notice form-notice--error" role="alert"><?= h($erro) ?></p>
            <?php endif; ?>

            <form class="auth-form" action="cadastro.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" id="perfil" name="perfil" value="<?= h($perfil) ?>">
                <div class="cadastro-abas" role="tablist" aria-label="Tipo de conta">
                    <button class="cadastro-aba<?= $perfil === 'participante' ? ' cadastro-aba--ativa' : '' ?>" id="aba-participante" type="button" role="tab" aria-selected="<?= $perfil === 'participante' ? 'true' : 'false' ?>" aria-controls="painel-participante" tabindex="<?= $perfil === 'participante' ? '0' : '-1' ?>" data-perfil="participante">Participante</button>
                    <button class="cadastro-aba<?= $perfil === 'organizador' ? ' cadastro-aba--ativa' : '' ?>" id="aba-organizador" type="button" role="tab" aria-selected="<?= $perfil === 'organizador' ? 'true' : 'false' ?>" aria-controls="painel-organizador" tabindex="<?= $perfil === 'organizador' ? '0' : '-1' ?>" data-perfil="organizador">Organizador</button>
                </div>
                <div id="painel-participante" class="cadastro-painel" role="tabpanel" aria-labelledby="aba-participante" <?= $perfil === 'organizador' ? 'hidden' : '' ?>>
                    <p class="ajuda">Encontre eventos acadêmicos e faça suas inscrições.</p>
                </div>
                <div id="painel-organizador" class="cadastro-painel" role="tabpanel" aria-labelledby="aba-organizador" <?= $perfil !== 'organizador' ? 'hidden' : '' ?>>
                    <div class="campo">
                        <label for="codigo-organizador">Código de organizador <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                        <input type="text" id="codigo-organizador" name="codigo_organizador" autocomplete="off" placeholder="Digite o código de acesso" <?= $perfil === 'organizador' ? 'required' : 'disabled' ?>>
                        <small class="ajuda">Necessário para criar uma conta de organizador.</small>
                    </div>
                </div>

                <div class="campo">
                    <label for="nome">Nome completo <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input type="text" id="nome" name="nome" autocomplete="name" placeholder="Digite seu nome completo" required>
                </div>

                <div class="campo">
                    <label for="email">E-mail <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" autocomplete="email" placeholder="seuemail@exemplo.com" required>
                </div>

                <div class="campo" id="campo-matricula" <?= $perfil === 'organizador' ? 'hidden' : '' ?>>
                    <label for="matricula">Matrícula <span class="opcional">(opcional)</span></label>
                    <input type="text" id="matricula" name="matricula" autocomplete="off" placeholder="Digite sua matrícula" <?= $perfil === 'organizador' ? 'disabled' : '' ?>>
                </div>

                <div class="campo">
                    <label for="senha">Senha <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" placeholder="Crie uma senha" aria-describedby="senha-ajuda" required>
                    <small id="senha-ajuda" class="ajuda">Use pelo menos 8 caracteres.</small>
                </div>

                <div class="campo">
                    <label for="confirmar-senha">Confirmar senha <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input type="password" id="confirmar-senha" name="confirmar-senha" autocomplete="new-password" minlength="8" placeholder="Digite sua senha novamente" required>
                </div>

                <label class="checkbox-linha checkbox-linha--termos" for="termos">
                    <input type="checkbox" id="termos" name="termos" required>
                    <span>Concordo com os <strong>termos de uso</strong> <span class="campo-obrigatorio" aria-hidden="true">*</span></span>
                </label>

                <button type="submit" class="botao">Criar conta</button>
            </form>

            <p class="rodape">Já tem uma conta? <a href="index.php">Entrar</a></p>
        </section>
    </main>

    <script>
        const senha = document.getElementById('senha');
        const confirmarSenha = document.getElementById('confirmar-senha');
        const perfil = document.getElementById('perfil');
        const codigoOrganizador = document.getElementById('codigo-organizador');
        const campoMatricula = document.getElementById('campo-matricula');
        const matricula = document.getElementById('matricula');
        const abasCadastro = document.querySelectorAll('.cadastro-aba');

        function selecionarPerfil(novoPerfil) {
            perfil.value = novoPerfil;
            document.getElementById('painel-participante').hidden = novoPerfil !== 'participante';
            document.getElementById('painel-organizador').hidden = novoPerfil !== 'organizador';
            codigoOrganizador.disabled = novoPerfil !== 'organizador';
            codigoOrganizador.required = novoPerfil === 'organizador';
            campoMatricula.hidden = novoPerfil === 'organizador';
            matricula.disabled = novoPerfil === 'organizador';

            abasCadastro.forEach((aba) => {
                const selecionada = aba.dataset.perfil === novoPerfil;
                aba.classList.toggle('cadastro-aba--ativa', selecionada);
                aba.setAttribute('aria-selected', String(selecionada));
                aba.tabIndex = selecionada ? 0 : -1;
            });
        }

        abasCadastro.forEach((aba) => {
            aba.addEventListener('click', () => selecionarPerfil(aba.dataset.perfil));
            aba.addEventListener('keydown', (evento) => {
                if (evento.key === 'ArrowLeft' || evento.key === 'ArrowRight') {
                    evento.preventDefault();
                    const outroPerfil = aba.dataset.perfil === 'participante' ? 'organizador' : 'participante';
                    selecionarPerfil(outroPerfil);
                    document.querySelector(`[data-perfil="${outroPerfil}"]`).focus();
                }
            });
        });

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
