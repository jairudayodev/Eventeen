<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/usuarios.php';

$usuario = requireRole('administrador');
$idRecebido = $_GET['id'] ?? $_POST['usuario_id'] ?? null;
$contaId = (is_string($idRecebido) || is_int($idRecebido)) ? filter_var($idRecebido, FILTER_VALIDATE_INT) : false;
if ($contaId === false || $contaId < 1) {
    header('Location: usuarios.php');
    exit;
}
$conta = buscarUsuario($pdo, $contaId);
if ($conta === null) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

$mensagem = null;
$valores = [
    'nome' => (string) $conta['nome'],
    'email' => (string) $conta['email'],
    'matricula' => (string) ($conta['matricula'] ?? ''),
    'perfil' => (string) $conta['perfil'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    foreach (array_keys($valores) as $campo) {
        $valor = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valor) ? trim($valor) : '';
    }
    try {
        atualizarUsuarioAdministracao(
            $pdo,
            $contaId,
            $valores['nome'],
            $valores['email'],
            $valores['matricula'],
            $valores['perfil']
        );
        if ($contaId === (int) $usuario['id'] && $valores['perfil'] !== 'administrador') {
            // O próprio administrador deixou o perfil: segue para a área do novo perfil.
            header('Location: ../' . profileHomePath($valores['perfil']));
            exit;
        }
        header('Location: usuarios.php?resultado=salvo');
        exit;
    } catch (DomainException $exception) {
        $mensagem = $exception->getMessage();
    }
}

renderManagementStart($usuario, 'Gerenciar usuário', 'Atualize os dados da conta e o perfil de acesso.', 'usuarios');
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice management-notice--error" role="alert"><?= h($mensagem) ?></p>
<?php endif; ?>
<form class="management-panel" method="post" action="usuario-form.php?id=<?= (int) $contaId ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="usuario_id" value="<?= (int) $contaId ?>">
    <div class="management-panel__heading">
        <h2><?= h($conta['nome']) ?></h2>
        <span class="management-badge"><?= h(rotuloPerfil((string) $conta['perfil'])) ?></span>
    </div>
    <div class="management-form-grid">
        <div class="management-field">
            <label for="nome-usuario">Nome completo</label>
            <input id="nome-usuario" class="management-input" name="nome" maxlength="150" required value="<?= h($valores['nome']) ?>">
        </div>
        <div class="management-field">
            <label for="email-usuario">E-mail</label>
            <input id="email-usuario" class="management-input" name="email" type="email" maxlength="190" required value="<?= h($valores['email']) ?>">
        </div>
        <div class="management-field">
            <label for="matricula-usuario">Matrícula <span class="management-muted">(opcional)</span></label>
            <input id="matricula-usuario" class="management-input" name="matricula" maxlength="40" value="<?= h($valores['matricula']) ?>">
        </div>
        <div class="management-field">
            <label for="perfil-usuario">Perfil de acesso</label>
            <select id="perfil-usuario" class="management-select" name="perfil">
                <?php foreach (PERFIS_USUARIO as $valor => $rotulo): ?>
                    <option value="<?= h($valor) ?>"<?= $valores['perfil'] === $valor ? ' selected' : '' ?>><?= h($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="management-muted">Organizadores gerenciam os eventos aos quais estão vinculados; administradores gerenciam usuários e permissões. O último administrador não pode ser revogado.</small>
        </div>
    </div>
    <p class="management-muted">Conta criada em <?= h(date('d/m/Y H:i', strtotime((string) $conta['criado_em']))) ?>. A alteração de perfil vale a partir da próxima página acessada pelo usuário.</p>
    <div class="management-actions management-actions--spaced">
        <button class="management-button" type="submit">Salvar alterações</button>
        <a class="management-button management-button--secondary" href="usuarios.php">Voltar</a>
    </div>
</form>
<?php renderManagementEnd($usuario); ?>
