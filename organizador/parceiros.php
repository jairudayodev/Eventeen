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
        if ($acao === 'parceiro') {
            salvarParceiro(
                $pdo,
                $usuarioId,
                programacaoTexto($_POST['nome'] ?? null, 'nome'),
                programacaoTexto($_POST['email'] ?? '', 'e-mail'),
                programacaoTexto($_POST['telefone'] ?? '', 'telefone')
            );
            header('Location: parceiros.php' . ($eventoId === null ? '?resultado=parceiro' : '?evento_id=' . $eventoId . '&resultado=parceiro'));
            exit;
        }
        if ($acao === 'patrocinio') {
            if ($eventoId === null) {
                throw new DomainException('Cadastre ou selecione um evento antes de registrar patrocínios.');
            }
            $parceiroId = programacaoId($_POST['parceiro_id'] ?? null);
            $tipoApoio = programacaoTexto($_POST['tipo_apoio'] ?? 'financeiro', 'tipo de apoio');
            $valorTexto = programacaoTexto($_POST['valor'] ?? '', 'valor');
            $valor = $tipoApoio === 'financeiro' || $valorTexto !== ''
                ? financeiroCentavos($valorTexto, $tipoApoio !== 'financeiro')
                : 0;
            salvarPatrocinio(
                $pdo,
                $eventoId,
                $usuarioId,
                $parceiroId,
                programacaoTexto($_POST['cota'] ?? null, 'cota'),
                programacaoTexto($_POST['contrapartida'] ?? '', 'contrapartida'),
                $valor,
                $tipoApoio,
                programacaoTexto($_POST['descricao_apoio'] ?? '', 'descrição do apoio')
            );
            header('Location: parceiros.php?evento_id=' . $eventoId . '&resultado=patrocinio');
            exit;
        }
        if ($acao === 'status') {
            $patrocinioId = programacaoId($_POST['patrocinio_id'] ?? null);
            $status = programacaoTexto($_POST['status'] ?? null, 'status');
            atualizarStatusPatrocinio($pdo, $patrocinioId, $usuarioId, $status);
            header('Location: parceiros.php' . ($eventoId === null ? '?resultado=status' : '?evento_id=' . $eventoId . '&resultado=status'));
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

$consulta = $pdo->prepare(
    'SELECT p.id, p.nome, p.email, p.telefone FROM parceiros p
     WHERE p.criado_por = :usuario_id
        OR EXISTS (
            SELECT 1 FROM usuarios usuario_admin
            WHERE usuario_admin.id = :admin_id AND usuario_admin.perfil = \'administrador\'
        )
     ORDER BY p.nome, p.id'
);
$consulta->execute(['usuario_id' => $usuarioId, 'admin_id' => $usuarioId]);
$parceiros = $consulta->fetchAll();
$patrocinios = [];
if ($eventoId !== null) {
    $consulta = $pdo->prepare(
        'SELECT pa.id, pa.tipo_apoio, pa.cota, pa.descricao_apoio, pa.contrapartida, pa.valor, pa.status,
                p.nome AS parceiro_nome, p.email AS parceiro_email
         FROM patrocinios pa
         JOIN parceiros p ON p.id = pa.parceiro_id
         WHERE pa.evento_id = :evento_id
         ORDER BY pa.criado_em DESC, pa.id DESC'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $patrocinios = $consulta->fetchAll();
}

$resultados = [
    'parceiro' => 'Parceiro cadastrado com sucesso.',
    'patrocinio' => 'Patrocínio registrado como em negociação.',
    'status' => 'Status do patrocínio atualizado.',
];
$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && is_string($resultado) && isset($resultados[$resultado])) {
    $mensagem = $resultados[$resultado];
}

renderManagementStart(
    $usuario,
    'Parceiros e patrocínios',
    'Cadastre parceiros e acompanhe apoios financeiros, materiais e institucionais de cada evento.',
    'mais'
);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if ($eventos): ?>
    <form class="management-toolbar management-event-picker" method="get" action="parceiros.php">
        <?php managementEventPicker($eventos, $eventoId, 'Abrir'); ?>
    </form>
<?php else: ?>
    <p class="management-notice">Cadastre um evento para registrar patrocínios.</p>
<?php endif; ?>

<form class="management-panel" method="post" action="parceiros.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <?php if ($eventoId !== null): ?><input type="hidden" name="evento_id" value="<?= $eventoId ?>"><?php endif; ?>
    <input type="hidden" name="acao" value="parceiro">
    <div class="management-panel__heading"><h2>Cadastrar parceiro</h2></div>
    <div class="management-form-grid">
        <div class="management-field">
            <label for="nome-parceiro">Nome</label>
            <input class="management-input" id="nome-parceiro" name="nome" maxlength="150" required>
        </div>
        <div class="management-field">
            <label for="email-parceiro">E-mail <span class="management-muted">(opcional)</span></label>
            <input class="management-input" id="email-parceiro" name="email" type="email" maxlength="190">
        </div>
        <div class="management-field">
            <label for="telefone-parceiro">Telefone <span class="management-muted">(opcional)</span></label>
            <input class="management-input" id="telefone-parceiro" name="telefone" maxlength="40">
        </div>
    </div>
    <div class="management-actions management-actions--spaced">
        <button class="management-button" type="submit">Salvar parceiro</button>
    </div>
</form>

<?php if ($eventoAtual !== null): ?>
    <form class="management-panel" method="post" action="parceiros.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
        <input type="hidden" name="acao" value="patrocinio">
        <div class="management-panel__heading"><h2>Adicionar patrocínio · <?= h($eventoAtual['titulo']) ?></h2></div>
        <?php if (!$parceiros): ?>
            <p class="management-muted">Cadastre um parceiro antes de registrar seu patrocínio.</p>
        <?php else: ?>
            <div class="management-form-grid">
                <div class="management-field">
                    <label for="parceiro-patrocinio">Parceiro</label>
                    <select class="management-select" id="parceiro-patrocinio" name="parceiro_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($parceiros as $parceiro): ?>
                            <option value="<?= (int) $parceiro['id'] ?>"><?= h($parceiro['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="management-field">
                    <label for="tipo-patrocinio">Tipo de apoio</label>
                    <select class="management-select" id="tipo-patrocinio" name="tipo_apoio" required>
                        <?php foreach (TIPOS_APOIO as $valorTipo => $rotuloTipo): ?>
                            <option value="<?= h($valorTipo) ?>"><?= h($rotuloTipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="management-field">
                    <label for="cota-patrocinio">Cota</label>
                    <input class="management-input" id="cota-patrocinio" name="cota" maxlength="80" required placeholder="Ouro, prata, apoio...">
                </div>
                <div class="management-field">
                    <label for="valor-patrocinio">Valor acordado (R$) <span class="management-muted">(somente apoio financeiro)</span></label>
                    <input class="management-input" id="valor-patrocinio" name="valor" inputmode="decimal" placeholder="5000,00">
                </div>
                <div class="management-field management-field--full">
                    <label for="descricao-patrocinio">Recurso oferecido <span class="management-muted">(obrigatório para apoio material ou institucional)</span></label>
                    <input class="management-input" id="descricao-patrocinio" name="descricao_apoio" maxlength="500" placeholder="Ex.: 200 camisetas, divulgação institucional, empréstimo de equipamentos">
                </div>
                <div class="management-field management-field--full">
                    <label for="contrapartida-patrocinio">Contrapartida <span class="management-muted">(opcional)</span></label>
                    <input class="management-input" id="contrapartida-patrocinio" name="contrapartida" maxlength="500" placeholder="Divulgação, estande, materiais...">
                </div>
            </div>
            <p class="management-muted">O patrocínio começa em negociação. Apoio financeiro exige valor positivo e, depois de confirmado, aceita pagamentos até o valor acordado. Apoios material e institucional exigem a descrição do recurso e não têm valor monetário.</p>
            <div class="management-actions management-actions--spaced">
                <button class="management-button" type="submit">Registrar patrocínio</button>
            </div>
        <?php endif; ?>
    </form>
    <section class="management-panel">
        <div class="management-panel__heading"><h2>Patrocínios · <?= h($eventoAtual['titulo']) ?></h2><span class="management-badge"><?= count($patrocinios) ?> contratos</span></div>
        <?php if (!$patrocinios): ?>
            <p class="management-muted">Nenhum patrocínio registrado para este evento.</p>
        <?php else: ?>
            <div class="management-table-wrap">
                <table class="management-table">
                    <thead><tr><th>Parceiro</th><th>Apoio</th><th>Contrapartida</th><th>Status</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach ($patrocinios as $patrocinio):
                            $rotulosStatus = ['negociacao' => 'Em negociação', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado'];
                            $classeStatus = ['negociacao' => 'management-badge--draft', 'cancelado' => 'management-badge--muted'];
                        ?>
                            <tr>
                                <td data-label="Parceiro"><strong><?= h($patrocinio['parceiro_nome']) ?></strong><?= $patrocinio['parceiro_email'] ? '<br>' . h($patrocinio['parceiro_email']) : '' ?></td>
                                <td data-label="Apoio"><strong><?= h(TIPOS_APOIO[$patrocinio['tipo_apoio']] ?? $patrocinio['tipo_apoio']) ?></strong> · <?= h($patrocinio['cota']) ?><br><?= $patrocinio['tipo_apoio'] === 'financeiro' ? h(financeiroMoeda($patrocinio['valor'])) : h($patrocinio['descricao_apoio'] ?? '') ?></td>
                                <td data-label="Contrapartida"><?= h($patrocinio['contrapartida'] ?: 'Não informada') ?></td>
                                <td data-label="Status"><span class="management-badge <?= h($classeStatus[$patrocinio['status']] ?? '') ?>"><?= h($rotulosStatus[$patrocinio['status']]) ?></span></td>
                                <td data-label="Ação">
                                    <?php if ($patrocinio['status'] !== 'cancelado'): ?>
                                        <form method="post" action="parceiros.php" class="management-actions">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                            <input type="hidden" name="patrocinio_id" value="<?= (int) $patrocinio['id'] ?>">
                                            <input type="hidden" name="acao" value="status">
                                            <?php if ($patrocinio['status'] === 'negociacao'): ?>
                                                <button class="management-button management-button--secondary" name="status" value="confirmado" type="submit">Confirmar</button>
                                            <?php endif; ?>
                                            <button class="management-button management-button--secondary" name="status" value="cancelado" type="submit" onclick="return confirm('Cancelar este patrocínio? Um patrocínio cancelado não pode ser reativado.');">Cancelar</button>
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

<section class="management-panel">
    <div class="management-panel__heading"><h2>Parceiros cadastrados</h2><span class="management-badge"><?= count($parceiros) ?> parceiros</span></div>
    <?php if (!$parceiros): ?>
        <p class="management-muted">Nenhum parceiro cadastrado.</p>
    <?php else: ?>
        <div class="management-rows">
            <?php foreach ($parceiros as $parceiro): ?>
                <div class="management-row">
                    <div class="management-row__main">
                        <strong><?= h($parceiro['nome']) ?></strong>
                        <span><?= h($parceiro['email'] ?: 'Sem e-mail') ?> · <?= h($parceiro['telefone'] ?: 'Sem telefone') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php renderManagementEnd($usuario); ?>
