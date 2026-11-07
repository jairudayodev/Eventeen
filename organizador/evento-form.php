<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');

$idRecebido = $_GET['id'] ?? $_POST['evento_id'] ?? null;
$eventoId = null;
if ($idRecebido !== null) {
    if (!is_string($idRecebido) && !is_int($idRecebido)) {
        header('Location: eventos.php');
        exit;
    }
    $eventoId = filter_var($idRecebido, FILTER_VALIDATE_INT);
    if ($eventoId === false || $eventoId < 1) {
        header('Location: eventos.php');
        exit;
    }
}
$evento = null;
$organizadoresSelecionados = [(int) $usuario['id']];

if ($eventoId !== null) {
    $consulta = $pdo->prepare(
        'SELECT e.*
         FROM eventos e
         WHERE e.id = :evento_id
           AND EXISTS (
               SELECT 1 FROM evento_organizadores eo
               WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
           )'
    );
    $consulta->execute(['evento_id' => $eventoId, 'usuario_id' => (int) $usuario['id']]);
    $evento = $consulta->fetch();
    if (!$evento) {
        http_response_code(404);
        exit('Evento não encontrado.');
    }
    $consulta = $pdo->prepare('SELECT usuario_id FROM evento_organizadores WHERE evento_id = :evento_id');
    $consulta->execute(['evento_id' => $eventoId]);
    $organizadoresSelecionados = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}

$consulta = $pdo->query("SELECT id, nome, email FROM usuarios WHERE perfil = 'organizador' ORDER BY nome");
$organizadoresDisponiveis = $consulta->fetchAll();
$mensagem = null;
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $texto = [];
    foreach (['titulo', 'descricao', 'categoria', 'local', 'inicio', 'fim', 'capacidade', 'status'] as $campo) {
        $valor = $_POST[$campo] ?? '';
        if (!is_string($valor)) {
            $mensagem = 'Verifique os dados informados e tente novamente.';
            $erro = true;
            break;
        }
        $texto[$campo] = trim($valor);
    }

    $idsEnviados = $_POST['organizadores'] ?? [];
    if (!$erro && !is_array($idsEnviados)) {
        $mensagem = 'Selecione ao menos um organizador.';
        $erro = true;
    }
    $idsOrganizadores = [];
    if (!$erro) {
        foreach ($idsEnviados as $idEnviado) {
            if (!is_string($idEnviado) && !is_int($idEnviado)) {
                $mensagem = 'Um dos organizadores selecionados é inválido.';
                $erro = true;
                break;
            }
            $id = filter_var($idEnviado, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                $mensagem = 'Um dos organizadores selecionados é inválido.';
                $erro = true;
                break;
            }
            $idsOrganizadores[] = $id;
        }
        $idsOrganizadores = array_values(array_unique($idsOrganizadores));
    }
    if (!$erro && $eventoId === null) {
        $idsOrganizadores[] = (int) $usuario['id'];
        $idsOrganizadores = array_values(array_unique($idsOrganizadores));
    }

    $dataInicio = null;
    $dataFim = null;
    if (!$erro) {
        $dataInicioObj = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $texto['inicio']);
        $errosInicio = DateTimeImmutable::getLastErrors();
        $dataFimObj = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $texto['fim']);
        $errosFim = DateTimeImmutable::getLastErrors();
        if (
            !$dataInicioObj || !$dataFimObj
            || ($errosInicio !== false && ($errosInicio['warning_count'] > 0 || $errosInicio['error_count'] > 0))
            || ($errosFim !== false && ($errosFim['warning_count'] > 0 || $errosFim['error_count'] > 0))
            || $dataInicioObj->format('Y-m-d\TH:i') !== $texto['inicio']
            || $dataFimObj->format('Y-m-d\TH:i') !== $texto['fim']
            || $dataFimObj <= $dataInicioObj
        ) {
            $mensagem = 'Informe datas e horários válidos, com o encerramento depois do início.';
            $erro = true;
        } else {
            $dataInicio = $dataInicioObj->format('Y-m-d H:i:s');
            $dataFim = $dataFimObj->format('Y-m-d H:i:s');
        }
    }

    $capacidade = !$erro ? filter_var($texto['capacidade'], FILTER_VALIDATE_INT) : false;
    if (!$erro && (
        $texto['titulo'] === ''
        || mb_strlen($texto['titulo'], 'UTF-8') > 180
        || strlen($texto['descricao']) > 65535
        || mb_strlen($texto['categoria'], 'UTF-8') > 80
        || mb_strlen($texto['local'], 'UTF-8') > 180
        || $texto['local'] === ''
        || $capacidade === false
        || $capacidade < 1
        || $capacidade > 4294967295
        || !in_array($texto['status'], ['rascunho', 'publicado'], true)
    )) {
        $mensagem = 'Preencha título, local e capacidade positiva. Verifique também os limites dos campos.';
        $erro = true;
    }
    if (!$erro && count($idsOrganizadores) < 1) {
        $mensagem = 'O evento deve ter ao menos um organizador.';
        $erro = true;
    }

    if (!$erro) {
        try {
            $pdo->beginTransaction();
            $statusAtual = null;
            $eventoSalvarId = $eventoId;
            if ($eventoId !== null) {
                $consulta = $pdo->prepare(
                    'SELECT e.status
                     FROM eventos e
                     WHERE e.id = :evento_id
                       AND EXISTS (
                           SELECT 1 FROM evento_organizadores eo
                           WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
                       )
                     FOR UPDATE'
                );
                $consulta->execute(['evento_id' => $eventoId, 'usuario_id' => (int) $usuario['id']]);
                $statusAtual = $consulta->fetchColumn();
                if ($statusAtual === false) {
                    throw new DomainException('Evento não encontrado ou você não tem permissão para alterá-lo.');
                }
            }

            $marcadores = implode(',', array_fill(0, count($idsOrganizadores), '?'));
            $consulta = $pdo->prepare(
                "SELECT id FROM usuarios WHERE perfil = 'organizador' AND id IN ({$marcadores})"
            );
            $consulta->execute($idsOrganizadores);
            if (count($consulta->fetchAll(PDO::FETCH_COLUMN)) !== count($idsOrganizadores)) {
                throw new DomainException('Selecione somente usuários que tenham perfil de organizador.');
            }

            if ($eventoId !== null) {
                $consulta = $pdo->prepare(
                    "SELECT COUNT(*) FROM inscricoes WHERE evento_id = :evento_id AND status = 'ativa'"
                );
                $consulta->execute(['evento_id' => $eventoId]);
                if ($capacidade < (int) $consulta->fetchColumn()) {
                    throw new DomainException('A capacidade não pode ser menor que o número de inscrições ativas.');
                }
                $novoStatus = in_array($statusAtual, ['cancelado', 'encerrado'], true)
                    ? $statusAtual
                    : $texto['status'];
                $atualizar = $pdo->prepare(
                    'UPDATE eventos SET
                        titulo = :titulo, descricao = :descricao, categoria = :categoria, local = :local,
                        data_inicio = :data_inicio, data_fim = :data_fim, capacidade = :capacidade, status = :status
                     WHERE id = :evento_id'
                );
                $atualizar->execute([
                    'titulo' => $texto['titulo'],
                    'descricao' => $texto['descricao'],
                    'categoria' => $texto['categoria'] !== '' ? $texto['categoria'] : 'Geral',
                    'local' => $texto['local'],
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'capacidade' => $capacidade,
                    'status' => $novoStatus,
                    'evento_id' => $eventoId,
                ]);
                $pdo->prepare('DELETE FROM evento_organizadores WHERE evento_id = :evento_id')
                    ->execute(['evento_id' => $eventoId]);
            } else {
                $inserir = $pdo->prepare(
                    'INSERT INTO eventos
                        (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
                     VALUES
                        (:titulo, :descricao, :categoria, :local, :data_inicio, :data_fim, :capacidade, :status)'
                );
                $inserir->execute([
                    'titulo' => $texto['titulo'],
                    'descricao' => $texto['descricao'],
                    'categoria' => $texto['categoria'] !== '' ? $texto['categoria'] : 'Geral',
                    'local' => $texto['local'],
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'capacidade' => $capacidade,
                    'status' => $texto['status'],
                ]);
                $eventoSalvarId = (int) $pdo->lastInsertId();
            }

            $associar = $pdo->prepare(
                'INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)'
            );
            foreach ($idsOrganizadores as $idOrganizador) {
                $associar->execute(['evento_id' => $eventoSalvarId, 'usuario_id' => $idOrganizador]);
            }
            $pdo->commit();
            header('Location: eventos.php?resultado=salvo');
            exit;
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = $exception->getMessage();
            $erro = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if (!$erro) {
        $organizadoresSelecionados = $idsOrganizadores;
    }
}

$valores = [
    'titulo' => (string) ($evento['titulo'] ?? ''),
    'descricao' => (string) ($evento['descricao'] ?? ''),
    'categoria' => (string) ($evento['categoria'] ?? ''),
    'local' => (string) ($evento['local'] ?? ''),
    'inicio' => isset($evento['data_inicio']) ? str_replace(' ', 'T', substr((string) $evento['data_inicio'], 0, 16)) : '',
    'fim' => isset($evento['data_fim']) ? str_replace(' ', 'T', substr((string) $evento['data_fim'], 0, 16)) : '',
    'capacidade' => (string) ($evento['capacidade'] ?? ''),
    'status' => in_array($evento['status'] ?? 'publicado', ['rascunho', 'publicado'], true)
        ? (string) ($evento['status'] ?? 'publicado')
        : 'publicado',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $valor) {
        if (isset($texto[$campo])) {
            $valores[$campo] = $texto[$campo];
        }
    }
    if ($evento !== null && in_array($evento['status'], ['cancelado', 'encerrado'], true)) {
        $valores['status'] = (string) $evento['status'];
    }
    if ($erro && isset($idsOrganizadores)) {
        $organizadoresSelecionados = $idsOrganizadores;
        if ($eventoId === null) {
            $organizadoresSelecionados[] = (int) $usuario['id'];
        }
    }
}

$inscritos = 0;
if ($eventoId !== null) {
    $consulta = $pdo->prepare(
        "SELECT COUNT(*) FROM inscricoes WHERE evento_id = :evento_id AND status = 'ativa'"
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $inscritos = (int) $consulta->fetchColumn();
}
$statusExibicao = (string) ($evento['status'] ?? $valores['status']);
$statusRotulos = ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado', 'encerrado' => 'Encerrado'];
$statusClasses = ['rascunho' => 'management-badge--draft', 'cancelado' => 'management-badge--red', 'encerrado' => 'management-badge--muted'];
renderManagementStart(
    $usuario,
    $evento === null ? 'Cadastro de evento' : 'Editar evento',
    $evento === null ? 'Informe os dados, a capacidade e os organizadores do evento.' : 'Atualize os dados e os organizadores vinculados.',
    'eventos',
    false
);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<form class="management-panel" method="post" action="evento-form.php<?= $eventoId !== null ? '?id=' . (int) $eventoId : '' ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <?php if ($eventoId !== null): ?><input type="hidden" name="evento_id" value="<?= (int) $eventoId ?>"><?php endif; ?>
    <div class="management-panel__heading">
        <h2>Informações do evento</h2>
        <span class="management-badge <?= h($statusClasses[$statusExibicao] ?? '') ?>"><?= h($statusRotulos[$statusExibicao] ?? $statusExibicao) ?></span>
    </div>
    <div class="management-form-grid">
        <div class="management-field management-field--full">
            <label for="titulo-evento">Nome do evento</label>
            <input id="titulo-evento" name="titulo" class="management-input" type="text" maxlength="180" required value="<?= h($valores['titulo']) ?>" placeholder="Ex.: Semana de Tecnologia 2026">
        </div>
        <div class="management-field">
            <label for="categoria-evento">Categoria</label>
            <input id="categoria-evento" name="categoria" class="management-input" type="text" maxlength="80" value="<?= h($valores['categoria']) ?>" placeholder="Ex.: Tecnologia">
        </div>
        <div class="management-field">
            <label for="local-evento">Local</label>
            <input id="local-evento" name="local" class="management-input" type="text" maxlength="180" required value="<?= h($valores['local']) ?>" placeholder="Ex.: Auditório">
        </div>
        <div class="management-field">
            <label for="inicio-evento">Início</label>
            <input id="inicio-evento" name="inicio" class="management-input" type="datetime-local" required value="<?= h($valores['inicio']) ?>">
        </div>
        <div class="management-field">
            <label for="fim-evento">Encerramento</label>
            <input id="fim-evento" name="fim" class="management-input" type="datetime-local" required value="<?= h($valores['fim']) ?>">
        </div>
        <div class="management-field">
            <label for="vagas-evento">Limite de participantes</label>
            <input id="vagas-evento" name="capacidade" class="management-input" type="number" min="1" step="1" required value="<?= h($valores['capacidade']) ?>">
            <?php if ($evento !== null): ?><small class="management-muted"><?= $inscritos ?> inscrição(ões) ativa(s); a capacidade não pode ficar abaixo desse número.</small><?php endif; ?>
        </div>
        <div class="management-field">
            <label for="status-evento">Publicação</label>
            <?php if (in_array($statusExibicao, ['cancelado', 'encerrado'], true)): ?>
                <input type="hidden" name="status" value="<?= h($statusExibicao) ?>">
                <p class="management-muted">Eventos cancelados ou encerrados não podem ser reabertos.</p>
            <?php else: ?>
                <select id="status-evento" name="status" class="management-select">
                    <option value="rascunho"<?= $valores['status'] === 'rascunho' ? ' selected' : '' ?>>Salvar como rascunho</option>
                    <option value="publicado"<?= $valores['status'] === 'publicado' ? ' selected' : '' ?>>Publicar evento</option>
                </select>
            <?php endif; ?>
        </div>
        <div class="management-field management-field--full">
            <label for="organizadores-evento">Organizadores</label>
            <select id="organizadores-evento" name="organizadores[]" class="management-select management-select--multiple" multiple required>
                <?php foreach ($organizadoresDisponiveis as $organizador): ?>
                    <option value="<?= (int) $organizador['id'] ?>"<?= in_array((int) $organizador['id'], $organizadoresSelecionados, true) ? ' selected' : '' ?>><?= h($organizador['nome']) ?> · <?= h($organizador['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="management-muted">Use Ctrl (Windows) ou Command (Mac) para selecionar mais de uma pessoa. O evento precisa ter ao menos um organizador.</small>
        </div>
        <div class="management-field management-field--full">
            <label for="descricao-evento">Descrição <span class="management-muted">(opcional)</span></label>
            <textarea id="descricao-evento" name="descricao" class="management-textarea" maxlength="16000" placeholder="Conte aos participantes sobre o evento"><?= h($valores['descricao']) ?></textarea>
        </div>
    </div>
    <div class="management-actions management-actions--spaced">
        <button class="management-button" type="submit">Salvar evento</button>
        <a class="management-button management-button--secondary" href="eventos.php">Voltar</a>
    </div>
</form>
<?php renderManagementEnd($usuario); ?>
