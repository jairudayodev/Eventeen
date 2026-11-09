<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/programacao.php';
$usuario = requireRole('organizador');
$eventos = programacaoEventos($pdo, (int) $usuario['id']);
$mensagem = null;
$erro = false;
$form = [
    'titulo' => '',
    'descricao' => '',
    'tipo' => 'Palestra',
    'inicio' => '',
    'fim' => '',
    'espaco_id' => '',
    'capacidade' => '',
];
$palestrantesSelecionados = [];
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
        $atividadeId = programacaoId($_POST['atividade_id'] ?? null, true);
        if ($eventoId === null) {
            throw new DomainException('Selecione um evento antes de cadastrar atividades.');
        }
        if ($acao === 'salvar') {
            $form = [
                'titulo' => programacaoTexto($_POST['titulo'] ?? null, 'título'),
                'descricao' => programacaoTexto($_POST['descricao'] ?? '', 'descrição'),
                'tipo' => programacaoTexto($_POST['tipo'] ?? null, 'tipo'),
                'inicio' => programacaoTexto($_POST['inicio'] ?? null, 'início'),
                'fim' => programacaoTexto($_POST['fim'] ?? null, 'fim'),
                'espaco_id' => programacaoTexto($_POST['espaco_id'] ?? null, 'espaço'),
                'capacidade' => programacaoTexto($_POST['capacidade'] ?? null, 'capacidade'),
            ];
            $espacoId = filter_var($form['espaco_id'], FILTER_VALIDATE_INT);
            $capacidade = filter_var($form['capacidade'], FILTER_VALIDATE_INT);
            if ($espacoId === false || $espacoId < 1 || $capacidade === false || $capacidade < 1 || $capacidade > 4294967295) {
                throw new DomainException('Selecione um espaço e informe uma capacidade inteira maior que zero.');
            }
            $idsRecebidos = $_POST['palestrantes'] ?? [];
            if (!is_array($idsRecebidos)) {
                throw new DomainException('A seleção de palestrantes é inválida.');
            }
            foreach ($idsRecebidos as $idRecebido) {
                $id = programacaoId($idRecebido);
                $palestrantesSelecionados[] = $id;
            }
            salvarAtividade(
                $pdo,
                $eventoId,
                (int) $usuario['id'],
                $atividadeId,
                $form['titulo'],
                $form['descricao'],
                $form['tipo'],
                $form['inicio'],
                $form['fim'],
                $espacoId,
                $capacidade,
                $palestrantesSelecionados
            );
            header('Location: atividades.php?evento_id=' . $eventoId . '&resultado=salvo');
            exit;
        }
        if ($acao === 'excluir' && $atividadeId !== null) {
            excluirAtividade($pdo, $eventoId, (int) $usuario['id'], $atividadeId);
            header('Location: atividades.php?evento_id=' . $eventoId . '&resultado=excluido');
            exit;
        }
        throw new DomainException('A ação solicitada não é válida.');
    }
} catch (DomainException $exception) {
    $mensagem = $exception->getMessage();
    $erro = true;
}

$eventoAtual = null;
$espacos = [];
$palestrantes = [];
$atividades = [];
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

    try {
        if (!$erro) {
            $editarId = programacaoId($_GET['editar'] ?? null, true);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editarId = programacaoId($_POST['atividade_id'] ?? null, true);
        }
    } catch (DomainException $exception) {
        $mensagem = $exception->getMessage();
        $erro = true;
    }

    $consulta = $pdo->prepare('SELECT id, nome, capacidade FROM espacos WHERE evento_id = :evento_id ORDER BY nome');
    $consulta->execute(['evento_id' => $eventoId]);
    $espacos = $consulta->fetchAll();
    $consulta = $pdo->prepare('SELECT id, nome FROM palestrantes WHERE evento_id = :evento_id ORDER BY nome');
    $consulta->execute(['evento_id' => $eventoId]);
    $palestrantes = $consulta->fetchAll();
    $consulta = $pdo->prepare(
        'SELECT a.id, a.titulo, a.descricao, a.tipo, a.inicio, a.fim, a.capacidade,
                s.nome AS espaco_nome,
                GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR \', \') AS palestrantes
         FROM atividades a
         JOIN espacos s ON s.id = a.espaco_id AND s.evento_id = a.evento_id
         LEFT JOIN atividade_palestrantes ap
           ON ap.atividade_id = a.id AND ap.evento_id = a.evento_id
         LEFT JOIN palestrantes p
           ON p.id = ap.palestrante_id AND p.evento_id = ap.evento_id
         WHERE a.evento_id = :evento_id
         GROUP BY a.id, a.titulo, a.descricao, a.tipo, a.inicio, a.fim, a.capacidade, s.nome
         ORDER BY a.inicio, a.id'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $atividades = $consulta->fetchAll();

    if ($editarId !== null && !$erro) {
        $consulta = $pdo->prepare(
            'SELECT id, titulo, descricao, tipo, inicio, fim, espaco_id, capacidade
             FROM atividades WHERE id = :id AND evento_id = :evento_id'
        );
        $consulta->execute(['id' => $editarId, 'evento_id' => $eventoId]);
        $atividade = $consulta->fetch();
        if (!$atividade) {
            http_response_code(404);
            exit('Atividade não encontrada neste evento.');
        }
        $form = [
            'titulo' => (string) $atividade['titulo'],
            'descricao' => (string) ($atividade['descricao'] ?? ''),
            'tipo' => (string) $atividade['tipo'],
            'inicio' => str_replace(' ', 'T', substr((string) $atividade['inicio'], 0, 16)),
            'fim' => str_replace(' ', 'T', substr((string) $atividade['fim'], 0, 16)),
            'espaco_id' => (string) $atividade['espaco_id'],
            'capacidade' => (string) $atividade['capacidade'],
        ];
        $consulta = $pdo->prepare(
            'SELECT palestrante_id FROM atividade_palestrantes WHERE atividade_id = :atividade_id'
        );
        $consulta->execute(['atividade_id' => $editarId]);
        $palestrantesSelecionados = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }
} elseif (!$eventos) {
    $mensagem = 'Cadastre um evento e vincule seu usuário como organizador para criar a programação.';
}

$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && $resultado === 'salvo') {
    $mensagem = 'Atividade salva na programação.';
} elseif ($mensagem === null && $resultado === 'excluido') {
    $mensagem = 'Atividade removida da programação.';
}

renderManagementStart($usuario, 'Atividades e programação', 'Monte a agenda do evento e valide horários, espaços e capacidades.', 'mais', false);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if ($eventos): ?>
    <form class="management-toolbar" method="get" action="atividades.php">
        <label class="management-muted" for="evento-atividades">Evento</label>
        <select id="evento-atividades" class="management-select" name="evento_id">
            <?php foreach ($eventos as $evento): ?>
                <option value="<?= (int) $evento['id'] ?>"<?= (int) $evento['id'] === $eventoId ? ' selected' : '' ?>><?= h($evento['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="management-button management-button--secondary" type="submit">Abrir programação</button>
        <?php if ($eventoId !== null): ?>
            <a class="management-button management-button--secondary" href="espacos.php?evento_id=<?= $eventoId ?>">Espaços</a>
            <a class="management-button management-button--secondary" href="palestrantes.php?evento_id=<?= $eventoId ?>">Palestrantes</a>
        <?php endif; ?>
    </form>
    <?php if ($eventoAtual !== null): ?>
        <?php if (!$espacos): ?>
            <p class="management-notice">Cadastre ao menos um <a href="espacos.php?evento_id=<?= $eventoId ?>">espaço</a> antes de agendar atividades.</p>
        <?php else: ?>
            <form class="management-panel" method="post" action="atividades.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                <input type="hidden" name="acao" value="salvar">
                <?php if ($editarId !== null): ?><input type="hidden" name="atividade_id" value="<?= $editarId ?>"><?php endif; ?>
                <div class="management-panel__heading"><h2><?= $editarId === null ? 'Adicionar atividade' : 'Editar atividade' ?></h2></div>
                <div class="management-form-grid">
                    <div class="management-field management-field--full">
                        <label for="titulo-atividade">Título</label>
                        <input class="management-input" id="titulo-atividade" name="titulo" maxlength="180" required value="<?= h($form['titulo']) ?>">
                    </div>
                    <div class="management-field">
                        <label for="tipo-atividade">Tipo</label>
                        <input class="management-input" id="tipo-atividade" name="tipo" maxlength="80" required value="<?= h($form['tipo']) ?>" placeholder="Palestra, oficina...">
                    </div>
                    <div class="management-field">
                        <label for="espaco-atividade">Espaço</label>
                        <select class="management-select" id="espaco-atividade" name="espaco_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($espacos as $espaco): ?>
                                <option value="<?= (int) $espaco['id'] ?>"<?= (string) $espaco['id'] === $form['espaco_id'] ? ' selected' : '' ?>><?= h($espaco['nome']) ?> · <?= (int) $espaco['capacidade'] ?> pessoas</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="management-field">
                        <label for="inicio-atividade">Início</label>
                        <input class="management-input" id="inicio-atividade" name="inicio" type="datetime-local" required value="<?= h($form['inicio']) ?>">
                    </div>
                    <div class="management-field">
                        <label for="fim-atividade">Fim</label>
                        <input class="management-input" id="fim-atividade" name="fim" type="datetime-local" required value="<?= h($form['fim']) ?>">
                    </div>
                    <div class="management-field">
                        <label for="capacidade-atividade">Capacidade (pessoas)</label>
                        <input class="management-input" id="capacidade-atividade" name="capacidade" type="number" min="1" max="4294967295" required value="<?= h($form['capacidade']) ?>">
                    </div>
                    <div class="management-field">
                        <label for="palestrantes-atividade">Palestrantes <span class="management-muted">(opcional)</span></label>
                        <select class="management-select management-select--multiple" id="palestrantes-atividade" name="palestrantes[]" multiple>
                            <?php foreach ($palestrantes as $palestrante): ?>
                                <option value="<?= (int) $palestrante['id'] ?>"<?= in_array((int) $palestrante['id'], $palestrantesSelecionados, true) ? ' selected' : '' ?>><?= h($palestrante['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="management-field management-field--full">
                        <label for="descricao-atividade">Descrição <span class="management-muted">(opcional)</span></label>
                        <textarea class="management-textarea" id="descricao-atividade" name="descricao" maxlength="16000"><?= h($form['descricao']) ?></textarea>
                    </div>
                </div>
                <p class="management-muted">A atividade deve ficar dentro do período do evento. Um mesmo espaço ou palestrante não pode ter atividades sobrepostas.</p>
                <div class="management-actions management-actions--spaced">
                    <button class="management-button" type="submit">Salvar atividade</button>
                    <?php if ($editarId !== null): ?><a class="management-button management-button--secondary" href="atividades.php?evento_id=<?= $eventoId ?>">Cancelar edição</a><?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
        <section class="management-panel">
            <div class="management-panel__heading"><h2>Programação · <?= h($eventoAtual['titulo']) ?></h2><span class="management-badge"><?= count($atividades) ?> atividades</span></div>
            <?php if (!$atividades): ?>
                <p class="management-muted">Nenhuma atividade agendada para este evento.</p>
            <?php else: ?>
                <div class="management-rows">
                    <?php foreach ($atividades as $atividade): ?>
                        <div class="management-row">
                            <div class="management-row__main">
                                <strong><?= h($atividade['titulo']) ?></strong>
                                <span><?= h(date('d/m/Y H:i', strtotime($atividade['inicio']))) ?>–<?= h(date('H:i', strtotime($atividade['fim']))) ?> · <?= h($atividade['espaco_nome']) ?> · <?= h($atividade['palestrantes'] ?: 'Sem palestrante') ?> · <?= (int) $atividade['capacidade'] ?> vagas</span>
                            </div>
                            <div class="management-actions">
                                <span class="management-badge"><?= h($atividade['tipo']) ?></span>
                                <a class="management-button management-button--secondary" href="atividades.php?evento_id=<?= $eventoId ?>&editar=<?= (int) $atividade['id'] ?>">Editar</a>
                                <form method="post" action="atividades.php" onsubmit="return confirm('Excluir esta atividade da programação?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                    <input type="hidden" name="atividade_id" value="<?= (int) $atividade['id'] ?>">
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
