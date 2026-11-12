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
        if ($acao === 'pagamento_registrar') {
            registrarPagamentoInscricao(
                $pdo,
                $usuarioId,
                programacaoId($_POST['inscricao_id'] ?? null),
                financeiroCentavos($_POST['valor'] ?? null),
                programacaoTexto($_POST['forma_pagamento'] ?? null, 'forma de pagamento'),
                programacaoTexto($_POST['status'] ?? null, 'status'),
                programacaoTexto($_POST['data_pagamento'] ?? '', 'data do pagamento')
            );
            header('Location: financeiro.php?evento_id=' . $eventoId . '&resultado=pagamento#pagamentos');
            exit;
        }
        if ($acao === 'pagamento_atualizar') {
            atualizarPagamentoInscricao(
                $pdo,
                $usuarioId,
                programacaoId($_POST['pagamento_id'] ?? null),
                programacaoTexto($_POST['operacao'] ?? null, 'operação'),
                programacaoTexto($_POST['data'] ?? null, 'data')
            );
            header('Location: financeiro.php?evento_id=' . $eventoId . '&resultado=pagamento_status#pagamentos');
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

$resumo = [
    'entradas' => '0.00', 'despesas' => '0.00', 'saldo_centavos' => 0, 'pendente' => '0.00',
    'lancamentos_pendentes' => 0, 'inscricoes_confirmadas' => '0.00', 'inscricoes_pendentes' => '0.00',
];
$lancamentos = [];
$patrocinios = [];
$pagamentos = [];
$valorInscricao = 0.0;
if ($eventoId !== null) {
    $resumo = resumoFinanceiroEvento($pdo, $eventoId);
    $lancamentos = listarLancamentosEvento($pdo, $eventoId);
    $pagamentos = listarPagamentosInscricoesEvento($pdo, $eventoId);
    $consulta = $pdo->prepare('SELECT valor_inscricao FROM eventos WHERE id = :evento_id');
    $consulta->execute(['evento_id' => $eventoId]);
    $valorInscricao = (float) $consulta->fetchColumn();
    $consulta = $pdo->prepare(
        "SELECT pa.id, p.nome, pa.cota, pa.valor
         FROM patrocinios pa
         JOIN parceiros p ON p.id = pa.parceiro_id
         WHERE pa.evento_id = :evento_id
           AND pa.status = 'confirmado'
           AND pa.tipo_apoio = 'financeiro'
         ORDER BY p.nome, pa.id"
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $patrocinios = $consulta->fetchAll();
}

$resultados = [
    'salvo' => 'Lançamento financeiro registrado.',
    'status' => 'Status do lançamento atualizado.',
    'pagamento' => 'Pagamento de inscrição registrado.',
    'pagamento_status' => 'Pagamento de inscrição atualizado.',
];
$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && is_string($resultado) && isset($resultados[$resultado])) {
    $mensagem = $resultados[$resultado];
}

renderManagementStart(
    $usuario,
    'Pagamentos e despesas',
    'Registre pagamentos de inscrição, entradas e despesas rateadas e confira o saldo de cada evento.',
    'mais'
);
$rotulosPagamento = ['pendente' => 'Pendente', 'confirmado' => 'Confirmado', 'estornado' => 'Estornado'];
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if (!$eventos): ?>
    <p class="management-notice">Cadastre um evento e vincule seu usuário como organizador para registrar movimentações financeiras.</p>
<?php else: ?>
    <form class="management-toolbar management-event-picker" method="get" action="financeiro.php">
        <?php managementEventPicker($eventos, $eventoId, 'Ver saldo'); ?>
    </form>

    <section class="management-kpis" aria-label="Resumo financeiro do evento">
        <?php managementKpi(financeiroMoeda($resumo['entradas']), 'Entradas recebidas', '＋'); ?>
        <?php managementKpi(financeiroMoeda($resumo['inscricoes_confirmadas']), 'Inscrições pagas', '♙'); ?>
        <?php managementKpi(financeiroMoeda($resumo['despesas']), 'Despesas pagas', '−', 'red'); ?>
        <?php managementKpi(financeiroMoedaCentavos($resumo['saldo_centavos']), 'Saldo do evento', '＝'); ?>
    </section>
    <p class="management-muted">Saldo = entradas recebidas + pagamentos de inscrição confirmados − despesas pagas atribuídas ao evento. Valores pendentes (<?= h(financeiroMoeda((float) $resumo['pendente'] + (float) $resumo['inscricoes_pendentes'])) ?>), cancelados e estornados não alteram o saldo.</p>

    <section class="management-panel" id="pagamentos">
        <div class="management-panel__heading">
            <h2>Pagamentos de inscrição · <?= h($eventoAtual['titulo']) ?></h2>
            <span class="management-badge"><?= $valorInscricao > 0 ? h(financeiroMoeda($valorInscricao)) . ' por inscrição' : 'Evento gratuito' ?></span>
        </div>
        <?php if ($valorInscricao <= 0 && !$pagamentos): ?>
            <p class="management-muted">Este evento é gratuito. Para registrar pagamentos de inscrição, informe o valor da inscrição no <a href="evento-form.php?id=<?= (int) $eventoId ?>">cadastro do evento</a>.</p>
        <?php elseif (!$pagamentos): ?>
            <p class="management-muted">Nenhuma inscrição Confirmada neste evento.</p>
        <?php else: ?>
            <p class="management-muted">Cada inscrição aceita um único pagamento. A confirmação exige a data do pagamento e o estorno pode ser registrado em até <?= PRAZO_ESTORNO_DIAS ?> dias após essa data. O sistema apenas registra as operações; não há cobrança on-line.</p>
            <div class="management-table-wrap">
                <table class="management-table">
                    <thead><tr><th>Participante</th><th>Pagamento</th><th>Status</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach ($pagamentos as $pagamento):
                            $classePagamento = ['pendente' => 'management-badge--draft', 'estornado' => 'management-badge--muted'][$pagamento['pagamento_status'] ?? ''] ?? '';
                        ?>
                            <tr>
                                <td data-label="Participante"><strong><?= h($pagamento['nome']) ?></strong><br><span class="management-muted"><?= h($pagamento['email']) ?><?= $pagamento['inscricao_status'] !== 'ativa' ? ' · inscrição cancelada' : '' ?></span></td>
                                <td data-label="Pagamento">
                                    <?php if ($pagamento['pagamento_id'] === null): ?>
                                        <span class="management-muted">Não registrado</span>
                                    <?php else: ?>
                                        <?= h(financeiroMoeda($pagamento['valor'])) ?> · <?= h(FORMAS_PAGAMENTO[$pagamento['forma_pagamento']] ?? $pagamento['forma_pagamento']) ?>
                                        <?php if ($pagamento['data_pagamento']): ?><br><span class="management-muted">Pago em <?= h(date('d/m/Y', strtotime($pagamento['data_pagamento']))) ?></span><?php endif; ?>
                                        <?php if ($pagamento['data_estorno']): ?><br><span class="management-muted">Estornado em <?= h(date('d/m/Y', strtotime($pagamento['data_estorno']))) ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($pagamento['pagamento_id'] === null): ?>—<?php else: ?>
                                        <span class="management-badge <?= h($classePagamento) ?>"><?= h($rotulosPagamento[$pagamento['pagamento_status']]) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Ação">
                                    <?php if ($pagamento['pagamento_id'] === null && $pagamento['inscricao_status'] === 'ativa' && $valorInscricao > 0): ?>
                                        <form method="post" action="financeiro.php" class="management-actions">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                            <input type="hidden" name="acao" value="pagamento_registrar">
                                            <input type="hidden" name="inscricao_id" value="<?= (int) $pagamento['inscricao_id'] ?>">
                                            <input class="management-input" name="valor" inputmode="decimal" required value="<?= h(number_format($valorInscricao, 2, ',', '')) ?>" aria-label="Valor pago" size="8">
                                            <select class="management-select" name="forma_pagamento" aria-label="Forma de pagamento">
                                                <?php foreach (FORMAS_PAGAMENTO as $valorForma => $rotuloForma): ?>
                                                    <option value="<?= h($valorForma) ?>"><?= h($rotuloForma) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="management-select" name="status" aria-label="Status do pagamento">
                                                <option value="confirmado">Confirmado</option>
                                                <option value="pendente">Pendente</option>
                                            </select>
                                            <input class="management-input" type="date" name="data_pagamento" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>" aria-label="Data do pagamento">
                                            <button class="management-button" type="submit">Registrar</button>
                                        </form>
                                    <?php elseif (in_array($pagamento['pagamento_status'], ['pendente', 'confirmado'], true)): ?>
                                        <form method="post" action="financeiro.php" class="management-actions">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                            <input type="hidden" name="acao" value="pagamento_atualizar">
                                            <input type="hidden" name="pagamento_id" value="<?= (int) $pagamento['pagamento_id'] ?>">
                                            <input class="management-input" type="date" name="data" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>" aria-label="Data da operação">
                                            <?php if ($pagamento['pagamento_status'] === 'pendente'): ?>
                                                <button class="management-button management-button--secondary" name="operacao" value="confirmar" type="submit">Confirmar pagamento</button>
                                            <?php else: ?>
                                                <button class="management-button management-button--secondary" name="operacao" value="estornar" type="submit" onclick="return confirm('Estornar este pagamento? O valor deixará de compor o saldo do evento.');">Estornar</button>
                                            <?php endif; ?>
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
            <div class="management-field management-field--full" data-somente="entrada">
                <label for="patrocinio-lancamento">Vincular a patrocínio financeiro confirmado <span class="management-muted">(opcional)</span></label>
                <select class="management-select" id="patrocinio-lancamento" name="patrocinio_id">
                    <option value="">Não vincular</option>
                    <?php foreach ($patrocinios as $patrocinio): ?>
                        <option value="<?= (int) $patrocinio['id'] ?>"><?= h($patrocinio['nome']) ?> · <?= h($patrocinio['cota']) ?> · <?= h(financeiroMoeda($patrocinio['valor'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="management-muted" data-somente="entrada">A entrada fica vinculada somente a este evento.</p>
        <details class="management-rateio" data-somente="despesa">
            <summary>Dividir esta despesa entre vários eventos (opcional)</summary>
            <p class="management-muted">Sem rateio, a despesa inteira é atribuída a este evento. Para dividir, informe o valor de cada evento; a soma deve ser igual ao valor total.</p>
            <div class="management-rows">
                <?php foreach ($eventos as $evento): ?>
                    <div class="management-row">
                        <div class="management-row__main">
                            <strong><?= h($evento['titulo']) ?></strong>
                            <span><?= h(date('d/m/Y', strtotime((string) $evento['data_inicio']))) ?></span>
                        </div>
                        <label class="management-field management-rateio__valor">
                            <span class="management-muted">R$</span>
                            <input class="management-input" name="rateios[<?= (int) $evento['id'] ?>]" inputmode="decimal" aria-label="Valor rateado para <?= h($evento['titulo']) ?>" placeholder="0,00">
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
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
                                            <button class="management-button management-button--secondary" name="status" value="cancelado" type="submit" onclick="return confirm('Cancelar este lançamento? Esta ação não pode ser desfeita.');">Cancelar</button>
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
<script>
    (function () {
        var tipo = document.getElementById('tipo-lancamento');
        if (!tipo) {
            return;
        }
        function atualizar() {
            document.querySelectorAll('[data-somente]').forEach(function (bloco) {
                var visivel = bloco.getAttribute('data-somente') === tipo.value;
                bloco.hidden = !visivel;
                bloco.querySelectorAll('input, select').forEach(function (campo) {
                    campo.disabled = !visivel;
                });
            });
        }
        tipo.addEventListener('change', atualizar);
        atualizar();
    }());
</script>
<?php renderManagementEnd($usuario); ?>
