<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/programacao.php';
$usuario = requireRole('organizador');
$eventos = programacaoEventos($pdo, (int) $usuario['id']);
$mensagem = null;
$erro = false;
$form = ['nome' => '', 'capacidade' => '', 'recursos' => ''];
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
        $espacoId = programacaoId($_POST['espaco_id'] ?? null, true);
        if ($eventoId === null) {
            throw new DomainException('Selecione um evento antes de cadastrar espaços.');
        }
        if ($acao === 'salvar') {
            $form = [
                'nome' => programacaoTexto($_POST['nome'] ?? null, 'nome'),
                'capacidade' => programacaoTexto($_POST['capacidade'] ?? null, 'capacidade'),
                'recursos' => programacaoTexto($_POST['recursos'] ?? '', 'recursos'),
            ];
            $capacidade = filter_var($form['capacidade'], FILTER_VALIDATE_INT);
            if ($capacidade === false || $capacidade < 1 || $capacidade > 4294967295) {
                throw new DomainException('Informe uma capacidade inteira maior que zero.');
            }
            salvarEspaco(
                $pdo,
                $eventoId,
                (int) $usuario['id'],
                $espacoId,
                $form['nome'],
                $capacidade,
                $form['recursos']
            );
            header('Location: espacos.php?evento_id=' . $eventoId . '&resultado=salvo');
            exit;
        }
        if ($acao === 'excluir' && $espacoId !== null) {
            excluirEspaco($pdo, $eventoId, (int) $usuario['id'], $espacoId);
            header('Location: espacos.php?evento_id=' . $eventoId . '&resultado=excluido');
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
$espacos = [];
if ($eventoId !== null) {
    foreach ($eventos as $item) {
        if ((int) $item['id'] === $eventoId) {
            $eventoAtual = $item;
            break;
        }
    }
    if ($eventoAtual === null) {
        http_response_code(404);
        exit('Evento não encontrado ou você não tem permissão para acessá-lo.');
    }

    $consulta = $pdo->prepare(
        'SELECT s.id, s.nome, s.capacidade, s.recursos, COUNT(a.id) AS total_atividades
         FROM espacos s
         LEFT JOIN atividades a ON a.espaco_id = s.id AND a.evento_id = s.evento_id
         WHERE s.evento_id = :evento_id
         GROUP BY s.id, s.nome, s.capacidade, s.recursos
         ORDER BY s.nome'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $espacos = $consulta->fetchAll();
    if ($editarId !== null && !$erro) {
        $consulta = $pdo->prepare(
            'SELECT id, nome, capacidade, recursos FROM espacos WHERE id = :id AND evento_id = :evento_id'
        );
        $consulta->execute(['id' => $editarId, 'evento_id' => $eventoId]);
        $espaco = $consulta->fetch();
        if (!$espaco) {
            http_response_code(404);
            exit('Espaço não encontrado neste evento.');
        }
        $form = [
            'nome' => (string) $espaco['nome'],
            'capacidade' => (string) $espaco['capacidade'],
            'recursos' => (string) ($espaco['recursos'] ?? ''),
        ];
    }
} elseif (!$eventos) {
    $mensagem = 'Cadastre um evento e vincule seu usuário como organizador para gerenciar espaços.';
}

$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && $resultado === 'salvo') {
    $mensagem = 'Espaço salvo com sucesso.';
} elseif ($mensagem === null && $resultado === 'excluido') {
    $mensagem = 'Espaço removido do evento.';
}

renderManagementStart($usuario, 'Espaços disponíveis', 'Cadastre os locais e suas capacidades para a programação do evento.', 'mais', false);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if ($eventos): ?>
    <form class="management-toolbar management-event-picker" method="get" action="espacos.php">
        <?php managementEventPicker($eventos, $eventoId, 'Abrir'); ?>
    </form>
    <?php if ($eventoAtual !== null): ?>
        <form class="management-panel" method="post" action="espacos.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($editarId !== null): ?><input type="hidden" name="espaco_id" value="<?= $editarId ?>"><?php endif; ?>
            <div class="management-panel__heading"><h2><?= $editarId === null ? 'Cadastrar espaço' : 'Editar espaço' ?></h2></div>
            <?php renderRequiredFieldsNote(); ?>
            <div class="management-form-grid">
                <div class="management-field">
                    <label for="nome-espaco">Nome <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input class="management-input" id="nome-espaco" name="nome" maxlength="120" required value="<?= h($form['nome']) ?>" placeholder="Ex.: Auditório">
                </div>
                <div class="management-field">
                    <label for="capacidade-espaco">Capacidade (pessoas) <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input class="management-input" id="capacidade-espaco" name="capacidade" type="number" min="1" max="4294967295" required value="<?= h($form['capacidade']) ?>">
                </div>
                <div class="management-field management-field--full">
                    <label for="recursos-espaco">Recursos <span class="management-muted">(opcional)</span></label>
                    <input class="management-input" id="recursos-espaco" name="recursos" maxlength="500" value="<?= h($form['recursos']) ?>" placeholder="Ex.: Projetor, áudio e Wi-Fi">
                </div>
            </div>
            <div class="management-actions management-actions--spaced">
                <button class="management-button" type="submit">Salvar espaço</button>
                <?php if ($editarId !== null): ?><a class="management-button management-button--secondary" href="espacos.php?evento_id=<?= $eventoId ?>">Cancelar edição</a><?php endif; ?>
            </div>
        </form>
        <section class="management-panel">
            <div class="management-panel__heading"><h2>Espaços · <?= h($eventoAtual['titulo']) ?></h2><span class="management-badge"><?= count($espacos) ?> cadastrados</span></div>
            <?php if (!$espacos): ?>
                <p class="management-muted">Nenhum espaço cadastrado para este evento.</p>
            <?php else: ?>
                <div class="management-rows">
                    <?php foreach ($espacos as $espaco): ?>
                        <div class="management-row">
                            <div class="management-row__main">
                                <strong><?= h($espaco['nome']) ?> · <?= (int) $espaco['capacidade'] ?> pessoas</strong>
                                <span><?= h($espaco['recursos'] ?: 'Sem recursos informados') ?> · <?= (int) $espaco['total_atividades'] ?> atividades</span>
                            </div>
                            <div class="management-actions">
                                <a class="management-button management-button--secondary" href="espacos.php?evento_id=<?= $eventoId ?>&editar=<?= (int) $espaco['id'] ?>">Editar</a>
                                <form method="post" action="espacos.php" onsubmit="return confirm('Excluir este espaço do evento?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                    <input type="hidden" name="espaco_id" value="<?= (int) $espaco['id'] ?>">
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
