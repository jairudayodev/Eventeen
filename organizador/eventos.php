<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
$usuario = requireRole('organizador');

$mensagem = null;
$erro = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $eventoIdRecebido = $_POST['evento_id'] ?? null;
    $eventoId = (is_string($eventoIdRecebido) || is_int($eventoIdRecebido))
        ? filter_var($eventoIdRecebido, FILTER_VALIDATE_INT)
        : false;
    $acaoRecebida = $_POST['acao'] ?? '';
    $acao = is_string($acaoRecebida) ? $acaoRecebida : '';

    if (!$eventoId || $eventoId < 1 || !in_array($acao, ['cancelar', 'encerrar'], true)) {
        $mensagem = 'Não foi possível identificar a ação solicitada.';
        $erro = true;
    } else {
        try {
            $pdo->beginTransaction();
            $consulta = $pdo->prepare(
                'SELECT e.id, e.status
                 FROM eventos e
                 WHERE e.id = :evento_id
                   AND EXISTS (
                       SELECT 1 FROM evento_organizadores eo
                       WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
                   )
                 FOR UPDATE'
            );
            $consulta->execute([
                'evento_id' => $eventoId,
                'usuario_id' => (int) $usuario['id'],
            ]);
            $evento = $consulta->fetch();
            if (!$evento) {
                throw new DomainException('Evento não encontrado ou você não tem permissão para alterá-lo.');
            }
            if (!in_array($evento['status'], ['rascunho', 'publicado'], true)) {
                throw new DomainException('Este evento já foi cancelado ou encerrado.');
            }
            if ($acao === 'encerrar' && $evento['status'] !== 'publicado') {
                throw new DomainException('Somente eventos publicados podem ser encerrados.');
            }

            $novoStatus = $acao === 'cancelar' ? 'cancelado' : 'encerrado';
            $atualizar = $pdo->prepare('UPDATE eventos SET status = :status WHERE id = :evento_id');
            $atualizar->execute(['status' => $novoStatus, 'evento_id' => $eventoId]);
            $pdo->commit();
            header('Location: eventos.php?resultado=' . $acao);
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
}

$statusRecebido = $_GET['status'] ?? '';
$statusFiltro = is_string($statusRecebido) ? $statusRecebido : '';
if (!in_array($statusFiltro, ['', 'rascunho', 'publicado', 'cancelado', 'encerrado'], true)) {
    $statusFiltro = '';
}
$termoRecebido = $_GET['q'] ?? '';
$termo = is_string($termoRecebido) ? trim($termoRecebido) : '';
$sql = "SELECT e.*,
            (SELECT COUNT(*) FROM inscricoes i
             WHERE i.evento_id = e.id AND i.status = 'ativa') AS inscritos,
            (SELECT GROUP_CONCAT(u.nome ORDER BY u.nome SEPARATOR ', ')
             FROM evento_organizadores eo
             JOIN usuarios u ON u.id = eo.usuario_id
             WHERE eo.evento_id = e.id) AS organizadores
        FROM eventos e
        WHERE EXISTS (
            SELECT 1 FROM evento_organizadores eo
            WHERE eo.evento_id = e.id AND eo.usuario_id = :usuario_id
        )";
$parametros = ['usuario_id' => (int) $usuario['id']];
if ($termo !== '') {
    $sql .= ' AND (e.titulo LIKE :termo_titulo OR e.local LIKE :termo_local)';
    $parametros['termo_titulo'] = '%' . $termo . '%';
    $parametros['termo_local'] = '%' . $termo . '%';
}
if ($statusFiltro !== '') {
    $sql .= ' AND e.status = :status';
    $parametros['status'] = $statusFiltro;
}
$sql .= ' ORDER BY e.data_inicio DESC, e.id DESC';
$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$eventos = $consulta->fetchAll();

$mensagensResultado = [
    'salvo' => 'Evento salvo com sucesso.',
    'cancelar' => 'Evento cancelado. Inscrições e demais registros foram preservados.',
    'encerrar' => 'Evento encerrado. Inscrições e demais registros foram preservados.',
];
$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && is_string($resultado) && isset($mensagensResultado[$resultado])) {
    $mensagem = $mensagensResultado[$resultado];
}

renderManagementStart($usuario, 'Gerenciar eventos', 'Consulte e administre os eventos sob sua organização.', 'eventos', false);
?>
<div class="management-toolbar">
    <form class="management-actions management-event-filters" method="get" action="eventos.php">
        <input class="management-search" type="search" name="q" value="<?= h($termo) ?>" placeholder="Buscar por nome ou local" aria-label="Buscar eventos">
        <select class="management-select" name="status" aria-label="Filtrar por status">
            <option value="">Todos os status</option>
            <?php foreach (['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado', 'encerrado' => 'Encerrado'] as $valor => $rotulo): ?>
                <option value="<?= h($valor) ?>"<?= $statusFiltro === $valor ? ' selected' : '' ?>><?= h($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="management-button management-button--secondary" type="submit">Filtrar</button>
    </form>
    <a class="management-button" href="evento-form.php">＋ Novo evento</a>
</div>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<div class="management-table-wrap">
    <table class="management-table">
        <thead><tr><th>Evento</th><th>Data e local</th><th>Organizadores</th><th>Inscritos</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
            <?php if (!$eventos): ?>
                <tr><td colspan="6">Nenhum evento encontrado para os filtros informados.</td></tr>
            <?php endif; ?>
            <?php foreach ($eventos as $evento):
                $statusRotulos = ['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado', 'encerrado' => 'Encerrado'];
                $statusClasses = ['rascunho' => 'management-badge--draft', 'cancelado' => 'management-badge--red', 'encerrado' => 'management-badge--muted'];
                $inscritos = (int) $evento['inscritos'];
            ?>
                <tr>
                    <td data-label="Evento"><strong><?= h($evento['titulo']) ?></strong></td>
                    <td data-label="Data e local"><?= h(formatDateTime($evento['data_inicio'])) ?><br><?= h(formatDateTime($evento['data_fim'])) ?><br><?= h($evento['local']) ?></td>
                    <td data-label="Organizadores"><?= h($evento['organizadores'] ?? '') ?></td>
                    <td data-label="Inscritos"><?= $inscritos ?> / <?= $evento['capacidade'] === null ? 'Sem limite' : (int) $evento['capacidade'] ?></td>
                    <td data-label="Status"><span class="management-badge <?= h($statusClasses[$evento['status']] ?? '') ?>"><?= h($statusRotulos[$evento['status']] ?? $evento['status']) ?></span></td>
                    <td data-label="Ações">
                        <div class="management-actions management-event-actions">
                            <a class="management-button management-button--secondary" href="evento-form.php?id=<?= (int) $evento['id'] ?>">Editar</a>
                            <?php if (in_array($evento['status'], ['rascunho', 'publicado'], true)): ?>
                                <?php if ($evento['status'] === 'publicado'): ?>
                                    <form method="post" action="eventos.php" onsubmit="return confirm('Encerrar este evento? As inscrições serão fechadas e o histórico será preservado.');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                        <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                                        <input type="hidden" name="acao" value="encerrar">
                                        <button class="management-button management-button--secondary" type="submit">Encerrar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="eventos.php" onsubmit="return confirm('Cancelar este evento? Inscrições e histórico serão preservados.');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                                    <input type="hidden" name="acao" value="cancelar">
                                    <button class="management-button management-button--secondary" type="submit">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php renderManagementEnd($usuario); ?>
