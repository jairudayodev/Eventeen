<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/programacao.php';
$usuario = requireRole('organizador');
$eventos = programacaoEventos($pdo, (int) $usuario['id']);
$mensagem = null;
$erro = false;
$form = ['nome' => '', 'email' => '', 'bio' => ''];
$eventoId = null;
$editarId = null;

try {
    $eventoId = programacaoId(
        $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['evento_id'] ?? null) : ($_GET['evento_id'] ?? null),
        true
    );
    if ($eventoId === null && $eventos) {
        $eventoId = (int) $eventos[0]['id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrfToken();
        $acao = programacaoTexto($_POST['acao'] ?? null, 'ação');
        $palestranteId = programacaoId($_POST['palestrante_id'] ?? null, true);
        if ($eventoId === null) {
            throw new DomainException('Selecione um evento antes de cadastrar palestrantes.');
        }
        if ($acao === 'salvar') {
            $form = [
                'nome' => programacaoTexto($_POST['nome'] ?? null, 'nome'),
                'email' => programacaoTexto($_POST['email'] ?? '', 'e-mail'),
                'bio' => programacaoTexto($_POST['bio'] ?? '', 'biografia'),
            ];
            salvarPalestrante(
                $pdo,
                $eventoId,
                (int) $usuario['id'],
                $palestranteId,
                $form['nome'],
                $form['email'],
                $form['bio']
            );
            header('Location: palestrantes.php?evento_id=' . $eventoId . '&resultado=salvo');
            exit;
        }
        if ($acao === 'excluir' && $palestranteId !== null) {
            excluirPalestrante($pdo, $eventoId, (int) $usuario['id'], $palestranteId);
            header('Location: palestrantes.php?evento_id=' . $eventoId . '&resultado=excluido');
            exit;
        }
        throw new DomainException('A ação solicitada não é válida.');
    }
} catch (DomainException $exception) {
    $mensagem = $exception->getMessage();
    $erro = true;
}

if ($eventoId !== null && !$erro) {
    try {
        $editarId = programacaoId($_GET['editar'] ?? null, true);
    } catch (DomainException $exception) {
        $mensagem = $exception->getMessage();
        $erro = true;
    }
}
$eventoAtual = null;
$palestrantes = [];
if ($eventoId !== null) {
    $eventoEncontrado = false;
    foreach ($eventos as $item) {
        if ((int) $item['id'] === $eventoId) {
            $eventoAtual = $item;
            $eventoEncontrado = true;
            break;
        }
    }
    if (!$eventoEncontrado) {
        http_response_code(404);
        exit('Evento não encontrado ou você não tem permissão para acessá-lo.');
    }

    $consulta = $pdo->prepare(
        'SELECT p.id, p.nome, p.email, p.bio, COUNT(ap.atividade_id) AS total_atividades
         FROM palestrantes p
         LEFT JOIN atividade_palestrantes ap
           ON ap.palestrante_id = p.id AND ap.evento_id = p.evento_id
         WHERE p.evento_id = :evento_id
         GROUP BY p.id, p.nome, p.email, p.bio
         ORDER BY p.nome'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $palestrantes = $consulta->fetchAll();
    if ($editarId !== null && !$erro) {
        $consulta = $pdo->prepare(
            'SELECT id, nome, email, bio FROM palestrantes WHERE id = :id AND evento_id = :evento_id'
        );
        $consulta->execute(['id' => $editarId, 'evento_id' => $eventoId]);
        $palestrante = $consulta->fetch();
        if (!$palestrante) {
            http_response_code(404);
            exit('Palestrante não encontrado neste evento.');
        }
        $form = [
            'nome' => (string) $palestrante['nome'],
            'email' => (string) ($palestrante['email'] ?? ''),
            'bio' => (string) ($palestrante['bio'] ?? ''),
        ];
    }
} elseif (!$eventos) {
    $mensagem = 'Cadastre um evento e vincule seu usuário como organizador para gerenciar palestrantes.';
}

$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && $resultado === 'salvo') {
    $mensagem = 'Palestrante salvo com sucesso.';
} elseif ($mensagem === null && $resultado === 'excluido') {
    $mensagem = 'Palestrante removido do evento.';
}

renderManagementStart($usuario, 'Palestrantes', 'Cadastre os convidados e associe-os às atividades do evento.', 'mais', false);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if ($eventos): ?>
    <form class="management-toolbar management-event-picker" method="get" action="palestrantes.php">
        <?php managementEventPicker($eventos, $eventoId, 'Abrir'); ?>
    </form>
    <?php if ($eventoAtual !== null): ?>
        <form class="management-panel" method="post" action="palestrantes.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($editarId !== null): ?><input type="hidden" name="palestrante_id" value="<?= $editarId ?>"><?php endif; ?>
            <div class="management-panel__heading"><h2><?= $editarId === null ? 'Adicionar palestrante' : 'Editar palestrante' ?></h2></div>
            <div class="management-form-grid">
                <div class="management-field">
                    <label for="nome-palestrante">Nome</label>
                    <input class="management-input" id="nome-palestrante" name="nome" maxlength="150" required value="<?= h($form['nome']) ?>">
                </div>
                <div class="management-field">
                    <label for="email-palestrante">E-mail <span class="management-muted">(opcional)</span></label>
                    <input class="management-input" id="email-palestrante" name="email" type="email" maxlength="190" value="<?= h($form['email']) ?>">
                </div>
                <div class="management-field management-field--full">
                    <label for="bio-palestrante">Biografia <span class="management-muted">(opcional)</span></label>
                    <textarea class="management-textarea" id="bio-palestrante" name="bio" maxlength="16000"><?= h($form['bio']) ?></textarea>
                </div>
            </div>
            <div class="management-actions management-actions--spaced">
                <button class="management-button" type="submit">Salvar palestrante</button>
                <?php if ($editarId !== null): ?><a class="management-button management-button--secondary" href="palestrantes.php?evento_id=<?= $eventoId ?>">Cancelar edição</a><?php endif; ?>
            </div>
        </form>
        <section class="management-panel">
            <div class="management-panel__heading"><h2>Palestrantes · <?= h($eventoAtual['titulo']) ?></h2><span class="management-badge"><?= count($palestrantes) ?> cadastrados</span></div>
            <?php if (!$palestrantes): ?>
                <p class="management-muted">Nenhum palestrante cadastrado para este evento.</p>
            <?php else: ?>
                <div class="management-rows">
                    <?php foreach ($palestrantes as $palestrante): ?>
                        <div class="management-row">
                            <div class="management-row__main">
                                <strong><?= h($palestrante['nome']) ?></strong>
                                <span><?= h($palestrante['email'] ?: 'Sem e-mail') ?> · <?= (int) $palestrante['total_atividades'] ?> atividades<?= $palestrante['bio'] ? ' · ' . h($palestrante['bio']) : '' ?></span>
                            </div>
                            <div class="management-actions">
                                <a class="management-button management-button--secondary" href="palestrantes.php?evento_id=<?= $eventoId ?>&editar=<?= (int) $palestrante['id'] ?>">Editar</a>
                                <form method="post" action="palestrantes.php" onsubmit="return confirm('Excluir este palestrante do evento?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                    <input type="hidden" name="palestrante_id" value="<?= (int) $palestrante['id'] ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <button class="management-button management-button--secondary" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php renderManagementEnd($usuario); ?>
