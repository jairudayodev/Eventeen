<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/financeiro.php';

$usuario = requireRole('organizador');
$usuarioId = (int) $usuario['id'];
$eventos = programacaoEventos($pdo, $usuarioId);
$mensagem = null;
$erro = false;
$eventoId = null;

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
        if ($acao === 'lancamento') {
            $tipo = programacaoTexto($_POST['tipo'] ?? null, 'tipo');
            $eventoIdLancamento = programacaoId($_POST['evento_id'] ?? null, true);
            $rateiosRecebidos = $_POST['rateios'] ?? [];
            if (!is_array($rateiosRecebidos)) {
                throw new DomainException('Os valores de rateio informados são inválidos.');
            }
            $rateios = [];
            foreach ($rateiosRecebidos as $id => $valor) {
                if (!is_string($valor) && !is_int($valor)) {
                    throw new DomainException('Um valor de rateio informado é inválido.');
                }
                if ($tipo === 'despesa' && trim((string) $valor) !== '') {
                    $rateios[$id] = $valor;
                }
            }
            $patrocinioId = programacaoId($_POST['patrocinio_id'] ?? null, true);
            salvarLancamentoFinanceiro(
                $pdo,
                $usuarioId,
                programacaoTexto($_POST['descricao'] ?? null, 'descrição'),
                $tipo,
                financeiroCentavos($_POST['valor'] ?? null),
                programacaoTexto($_POST['data_movimentacao'] ?? null, 'data'),
                programacaoTexto($_POST['status'] ?? null, 'status'),
                $eventoIdLancamento,
                $rateios,
                $patrocinioId
            );
            header('Location: financeiro.php' . ($eventoId === null ? '?resultado=salvo' : '?evento_id=' . $eventoId . '&resultado=salvo'));
            exit;
        }
        if ($acao === 'status') {
            $lancamentoId = programacaoId($_POST['lancamento_id'] ?? null);
            $status = programacaoTexto($_POST['status'] ?? null, 'status');
            atualizarStatusLancamento($pdo, $lancamentoId, $usuarioId, $status);
            header('Location: financeiro.php' . ($eventoId === null ? '?resultado=status' : '?evento_id=' . $eventoId . '&resultado=status'));
            exit;
        }
        throw new DomainException('A ação solicitada não é válida.');
    }
} catch (DomainException $exception) {
    $mensagem = $exception->getMessage();
    $erro = true;
}

$eventoAtual = null;
foreach ($eventos as $evento) {
    if ((int) $evento['id'] === $eventoId) {
        $eventoAtual = $evento;
        break;
    }
}
if ($eventoId !== null && $eventoAtual === null) {
    http_response_code(404);
    exit('Evento não encontrado ou você não tem permissão para acessá-lo.');
}

$resumo = ['entradas' => '0.00', 'despesas' => '0.00', 'saldo_centavos' => 0, 'pendente' => '0.00', 'lancamentos_pendentes' => 0];
$lancamentos = [];
$patrocinios = [];
if ($eventoId !== null) {
    $resumo = resumoFinanceiroEvento($pdo, $eventoId);
    $lancamentos = listarLancamentosEvento($pdo, $eventoId);
    $consulta = $pdo->prepare(
        "SELECT pa.id, p.nome, pa.cota, pa.valor
         FROM patrocinios pa
         JOIN parceiros p ON p.id = pa.parceiro_id
         WHERE pa.evento_id = :evento_id
           AND pa.status = 'confirmado'
         ORDER BY p.nome, pa.id"
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $patrocinios = $consulta->fetchAll();
}

$resultados = [
    'salvo' => 'Lançamento financeiro registrado.',
    'status' => 'Status do lançamento atualizado.',
];
$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && is_string($resultado) && isset($resultados[$resultado])) {
    $mensagem = $resultados[$resultado];
}

renderManagementStart(
    $usuario,
    'Pagamentos e despesas',
    'Registre movimentações e confira o saldo e as despesas rateadas de cada evento.',
    'mais',
    false
);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if (!$eventos): ?>
    <p class="management-notice">Cadastre um evento e vincule seu usuário como organizador para registrar movimentações financeiras.</p>
<?php else: ?>
    <form class="management-toolbar" method="get" action="financeiro.php">
        <label class="management-muted" for="evento-financeiro">Evento</label>
        <select id="evento-financeiro" class="management-select" name="evento_id">
            <?php foreach ($eventos as $evento): ?>
                <option value="<?= (int) $evento['id'] ?>"<?= (int) $evento['id'] === $eventoId ? ' selected' : '' ?>><?= h($evento['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="management-button management-button--secondary" type="submit">Ver saldo</button>
    </form>

    <section class="management-kpis" aria-label="Resumo financeiro do evento">
        <?php managementKpi(financeiroMoeda($resumo['entradas']), 'Entradas recebidas', '＋'); ?>
        <?php managementKpi(financeiroMoeda($resumo['despesas']), 'Despesas pagas', '−', 'red'); ?>
        <?php managementKpi(financeiroMoedaCentavos($resumo['saldo_centavos']), 'Saldo do evento', '＝'); ?>
        <?php managementKpi((string) $resumo['lancamentos_pendentes'], 'Lançamentos pendentes', '◷', 'draft'); ?>
    </section>

    <form class="management-panel" method="post" action="financeiro.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="acao" value="lancamento">
        <div class="management-panel__heading"><h2>Novo lançamento · <?= h($eventoAtual['titulo']) ?></h2></div>
        <div class="management-form-grid">
            <div class="management-field management-field--full">
                <label for="descricao-lancamento">Descrição</label>
                <input class="management-input" id="descricao-lancamento" name="descricao" maxlength="180" required placeholder="Ex.: Patrocínio, locação de equipamentos">
            </div>
            <div class="management-field">
                <label for="tipo-lancamento">Tipo</label>
                <select class="management-select" id="tipo-lancamento" name="tipo" required>
                    <option value="entrada">Entrada</option>
                    <option value="despesa">Despesa</option>
                </select>
            </div>
            <div class="management-field">
                <label for="valor-lancamento">Valor total (R$)</label>
                <input class="management-input" id="valor-lancamento" name="valor" inputmode="decimal" required placeholder="1250,00">
            </div>
            <div class="management-field">
                <label for="data-lancamento">Data</label>
                <input class="management-input" id="data-lancamento" name="data_movimentacao" type="date" required value="<?= h(date('Y-m-d')) ?>">
            </div>
            <div class="management-field">
                <label for="status-lancamento">Status</label>
                <select class="management-select" id="status-lancamento" name="status" required>
                    <option value="pendente">Pendente</option>
                    <option value="realizado">Realizado</option>
                </select>
            </div>
            <div class="management-field management-field--full">
                <label for="patrocinio-lancamento">Vincular a patrocínio confirmado <span class="management-muted">(opcional, apenas entradas)</span></label>
                <select class="management-select" id="patrocinio-lancamento" name="patrocinio_id">
                    <option value="">Não vincular</option>
                    <?php foreach ($patrocinios as $patrocinio): ?>
                        <option value="<?= (int) $patrocinio['id'] ?>"><?= h($patrocinio['nome']) ?> · <?= h($patrocinio['cota']) ?> · <?= h(financeiroMoeda($patrocinio['valor'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="management-muted">Entradas ficam vinculadas somente ao evento selecionado. Despesas sem rateio também são atribuídas a ele; para dividir uma despesa, preencha os valores por evento abaixo. A soma deve corresponder ao valor total.</p>
        <div class="management-rows">
            <?php foreach ($eventos as $evento): ?>
                <div class="management-row">
                    <div class="management-row__main">
                        <strong><?= h($evento['titulo']) ?></strong>
                        <span>Valor da despesa atribuído a este evento (opcional)</span>
                    </div>
                    <label class="management-field">
                        <span class="management-muted">R$</span>
                        <input class="management-input" name="rateios[<?= (int) $evento['id'] ?>]" inputmode="decimal" aria-label="Valor rateado para <?= h($evento['titulo']) ?>" placeholder="0,00">
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
        <div class="management-actions management-actions--spaced">
            <button class="management-button" type="submit">Registrar lançamento</button>
        </div>
    </form>

    <section class="management-panel">
        <div class="management-panel__heading"><h2>Movimentações · <?= h($eventoAtual['titulo']) ?></h2><span class="management-badge"><?= count($lancamentos) ?> lançamentos</span></div>
        <?php if (!$lancamentos): ?>
            <p class="management-muted">Nenhuma movimentação financeira registrada para este evento.</p>
        <?php else: ?>
            <div class="management-table-wrap">
                <table class="management-table">
                    <thead><tr><th>Descrição</th><th>Tipo</th><th>Data</th><th>Valor no evento</th><th>Eventos do rateio</th><th>Status</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach ($lancamentos as $lancamento):
                            $rotulosTipo = ['entrada' => 'Entrada', 'despesa' => 'Despesa'];
                            $rotulosStatus = ['pendente' => 'Pendente', 'realizado' => $lancamento['tipo'] === 'entrada' ? 'Recebido' : 'Pago', 'cancelado' => 'Cancelado'];
                            $classeStatus = ['pendente' => 'management-badge--draft', 'cancelado' => 'management-badge--muted'];
                        ?>
                            <tr>
                                <td data-label="Descrição"><strong><?= h($lancamento['descricao']) ?></strong><?= $lancamento['parceiro_nome'] ? '<br>' . h($lancamento['parceiro_nome']) : '' ?></td>
                                <td data-label="Tipo"><?= h($rotulosTipo[$lancamento['tipo']]) ?></td>
                                <td data-label="Data"><?= h(date('d/m/Y', strtotime($lancamento['data_movimentacao']))) ?></td>
                                <td data-label="Valor no evento"><?= h(financeiroMoeda($lancamento['valor_evento'])) ?> <span class="management-muted">(total <?= h(financeiroMoeda($lancamento['valor'])) ?>)</span></td>
                                <td data-label="Eventos do rateio"><?= h($lancamento['eventos']) ?></td>
                                <td data-label="Status"><span class="management-badge <?= h($classeStatus[$lancamento['status']] ?? '') ?>"><?= h($rotulosStatus[$lancamento['status']]) ?></span></td>
                                <td data-label="Ação">
                                    <?php if ($lancamento['status'] === 'pendente'): ?>
                                        <form method="post" action="financeiro.php" class="management-actions">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                            <input type="hidden" name="lancamento_id" value="<?= (int) $lancamento['id'] ?>">
                                            <input type="hidden" name="acao" value="status">
                                            <button class="management-button management-button--secondary" name="status" value="realizado" type="submit">Confirmar</button>
                                            <button class="management-button management-button--secondary" name="status" value="cancelado" type="submit">Cancelar</button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php renderManagementEnd($usuario); ?>
