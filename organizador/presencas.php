<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gestao.php';
require_once __DIR__ . '/../includes/programacao.php';
require_once __DIR__ . '/../includes/presencas.php';

$usuario = requireRole('organizador');
$organizadorId = (int) $usuario['id'];
$eventos = programacaoEventos($pdo, $organizadorId);
$mensagem = null;
$erro = false;
$eventoId = null;
$atividadeId = null;

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
        if ($eventoId === null) {
            throw new DomainException('Selecione um evento antes de registrar presenças.');
        }
        $atividadeId = programacaoId($_POST['atividade_id'] ?? null);
        $acao = programacaoTexto($_POST['acao'] ?? null, 'ação');
        if ($acao === 'registrar_lote') {
            $selecionados = $_POST['participantes'] ?? [];
            if (!is_array($selecionados) || $selecionados === []) {
                throw new DomainException('Selecione ao menos um participante para registrar a presença.');
            }
            $quantidade = 0;
            foreach (array_unique($selecionados) as $selecionado) {
                registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $atividadeId, programacaoId($selecionado), true);
                $quantidade++;
            }
            header('Location: presencas.php?evento_id=' . $eventoId . '&atividade_id=' . $atividadeId . '&resultado=lote&quantidade=' . $quantidade);
            exit;
        }
        $participanteId = programacaoId($_POST['participante_id'] ?? null);
        if (!in_array($acao, ['registrar', 'remover'], true)) {
            throw new DomainException('A ação solicitada não é válida.');
        }

        registrarPresencaAtividade(
            $pdo,
            $eventoId,
            $organizadorId,
            $atividadeId,
            $participanteId,
            $acao === 'registrar'
        );
        header('Location: presencas.php?evento_id=' . $eventoId . '&atividade_id=' . $atividadeId . '&resultado=' . $acao);
        exit;
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

$atividades = [];
$participantes = [];
if ($eventoId !== null) {
    $consulta = $pdo->prepare(
        'SELECT id, titulo, inicio, fim
         FROM atividades WHERE evento_id = :evento_id
         ORDER BY inicio, id'
    );
    $consulta->execute(['evento_id' => $eventoId]);
    $atividades = $consulta->fetchAll();

    try {
        $atividadeId = programacaoId(
            $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['atividade_id'] ?? null) : ($_GET['atividade_id'] ?? null),
            true
        );
    } catch (DomainException $exception) {
        $mensagem = $exception->getMessage();
        $erro = true;
    }
    if ($atividadeId === null && $atividades && !$erro) {
        $atividadeId = (int) $atividades[0]['id'];
    }

    $atividadeSelecionada = null;
    foreach ($atividades as $atividade) {
        if ((int) $atividade['id'] === $atividadeId) {
            $atividadeSelecionada = $atividade;
            break;
        }
    }
    if ($atividadeId !== null && $atividadeSelecionada === null && !$erro) {
        $mensagem = 'Selecione uma atividade deste evento.';
        $erro = true;
    }

    if ($atividadeSelecionada !== null) {
        $consulta = $pdo->prepare(
            'SELECT i.usuario_id, u.nome, u.email, pa.confirmado_em
             FROM inscricoes i
             JOIN usuarios u ON u.id = i.usuario_id
             LEFT JOIN presencas_atividades pa
               ON pa.atividade_id = :atividade_id
              AND pa.evento_id = i.evento_id
              AND pa.usuario_id = i.usuario_id
             WHERE i.evento_id = :evento_id AND i.status = \'ativa\'
             ORDER BY u.nome, u.id'
        );
        $consulta->execute(['atividade_id' => $atividadeId, 'evento_id' => $eventoId]);
        $participantes = $consulta->fetchAll();
    }
}

$resultado = $_GET['resultado'] ?? '';
if ($mensagem === null && $resultado === 'registrar') {
    $mensagem = 'Presença da atividade confirmada.';
} elseif ($mensagem === null && $resultado === 'remover') {
    $mensagem = 'Confirmação de presença removida.';
} elseif ($mensagem === null && $resultado === 'lote') {
    $quantidade = max(0, (int) ($_GET['quantidade'] ?? 0));
    $mensagem = $quantidade === 1 ? 'Presença registrada para 1 participante.' : "Presença registrada para {$quantidade} participantes.";
}

renderManagementStart(
    $usuario,
    'Inscrições e presenças',
    'Registre a presença de participantes inscritos em cada atividade do evento.',
    'presencas',
    false
);
?>
<?php if ($mensagem !== null): ?>
    <p class="management-notice<?= $erro ? ' management-notice--error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= h($mensagem) ?></p>
<?php endif; ?>
<?php if (!$eventos): ?>
    <p class="management-notice">Cadastre um evento para registrar presenças.</p>
<?php else: ?>
    <form class="management-toolbar management-event-picker" method="get" action="presencas.php">
        <?php managementEventPicker($eventos, $eventoId, 'Abrir'); ?>
    </form>

    <?php if ($eventoAtual !== null): ?>
        <?php if (!$atividades): ?>
            <?php
            $consultaInscritos = $pdo->prepare(
                "SELECT u.nome, u.email, i.status, i.inscrito_em
                 FROM inscricoes i JOIN usuarios u ON u.id = i.usuario_id
                 WHERE i.evento_id = :evento_id
                 ORDER BY i.status, u.nome"
            );
            $consultaInscritos->execute(['evento_id' => $eventoId]);
            $inscritosEvento = $consultaInscritos->fetchAll();
            ?>
            <p class="management-notice">Este evento ainda não possui atividades cadastradas. Cadastre a <a href="atividades.php?evento_id=<?= (int) $eventoId ?>">programação</a> para registrar presenças.</p>
            <div class="management-table-wrap">
                <table class="management-table">
                    <thead><tr><th>Participante</th><th>E-mail</th><th>Inscrição</th><th>Data da inscrição</th></tr></thead>
                    <tbody>
                        <?php if (!$inscritosEvento): ?>
                            <tr><td colspan="4">Nenhuma inscrição neste evento.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($inscritosEvento as $inscrito): ?>
                            <tr>
                                <td data-label="Participante"><strong><?= h($inscrito['nome']) ?></strong></td>
                                <td data-label="E-mail"><?= h($inscrito['email']) ?></td>
                                <td data-label="Inscrição"><span class="management-badge<?= $inscrito['status'] === 'ativa' ? '' : ' management-badge--muted' ?>"><?= $inscrito['status'] === 'ativa' ? 'Confirmada' : 'Cancelada' ?></span></td>
                                <td data-label="Data da inscrição"><?= h(date('d/m/Y H:i', strtotime((string) $inscrito['inscrito_em']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <form class="management-toolbar management-event-picker" method="get" action="presencas.php">
                <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                <label class="management-event-picker__label" for="atividade-presencas">Atividade</label>
                <select id="atividade-presencas" class="management-select management-event-picker__select" name="atividade_id">
                    <?php foreach ($atividades as $atividade): ?>
                        <option value="<?= (int) $atividade['id'] ?>"<?= (int) $atividade['id'] === $atividadeId ? ' selected' : '' ?>>
                            <?= h($atividade['titulo']) ?> · <?= h(date('d/m/Y H\hi', strtotime($atividade['inicio']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="management-button management-button--secondary" type="submit">Abrir</button>
            </form>
        <?php endif; ?>

        <?php if ($atividadeSelecionada !== null): ?>
            <?php
            $totalInscritos = count($participantes);
            $totalPresencas = count(array_filter($participantes, static fn (array $item): bool => $item['confirmado_em'] !== null));
            $pendentes = $totalInscritos - $totalPresencas;
            $taxaPresenca = $totalInscritos > 0 ? (int) round($totalPresencas * 100 / $totalInscritos) . '%' : '0%';
            ?>
            <section class="management-kpis" aria-label="Resumo da atividade">
                <?php managementKpi((string) $totalInscritos, 'Inscrições Confirmadas', '♙'); ?>
                <?php managementKpi((string) $totalPresencas, 'Presenças confirmadas', '✓'); ?>
                <?php managementKpi((string) $pendentes, 'Aguardando confirmação', '◷'); ?>
                <?php managementKpi($taxaPresenca, 'Taxa de presença', '◉'); ?>
            </section>
            <?php if ($pendentes > 0): ?>
                <form id="presenca-lote" class="management-toolbar management-event-picker" method="post" action="presencas.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                    <input type="hidden" name="atividade_id" value="<?= (int) $atividadeSelecionada['id'] ?>">
                    <input type="hidden" name="acao" value="registrar_lote">
                    <label class="management-check-all"><input type="checkbox" id="marcar-todos"> Selecionar todos os pendentes</label>
                    <input class="management-search" type="search" id="filtro-participantes" placeholder="Filtrar por nome ou e-mail" aria-label="Filtrar participantes">
                    <button class="management-button" type="submit">Registrar presença dos selecionados</button>
                </form>
            <?php endif; ?>
            <div class="management-table-wrap">
                <table class="management-table" id="tabela-presencas">
                    <thead><tr><th>Selecionar</th><th>Participante</th><th>E-mail</th><th>Inscrição</th><th>Presença na atividade</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php if (!$participantes): ?>
                            <tr><td colspan="6">Nenhuma inscrição Confirmada para este evento.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($participantes as $participante): ?>
                            <tr data-busca="<?= h(mb_strtolower($participante['nome'] . ' ' . $participante['email'], 'UTF-8')) ?>">
                                <td data-label="Selecionar">
                                    <?php if ($participante['confirmado_em'] === null): ?>
                                        <input type="checkbox" class="presenca-pendente" name="participantes[]" value="<?= (int) $participante['usuario_id'] ?>" form="presenca-lote" aria-label="Selecionar <?= h($participante['nome']) ?>">
                                    <?php else: ?>
                                        <span class="management-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Participante"><strong><?= h($participante['nome']) ?></strong></td>
                                <td data-label="E-mail"><?= h($participante['email']) ?></td>
                                <td data-label="Inscrição"><span class="management-badge">Confirmada</span></td>
                                <td data-label="Presença na atividade">
                                    <?php if ($participante['confirmado_em'] !== null): ?>
                                        <span class="management-check management-check--present" aria-label="Presença confirmada">✓</span>
                                        <span class="management-muted"><?= h(date('d/m/Y H:i', strtotime($participante['confirmado_em']))) ?></span>
                                    <?php else: ?>
                                        <span class="management-check" aria-label="Presença pendente">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Ação">
                                    <form method="post" action="presencas.php"<?= $participante['confirmado_em'] !== null ? ' data-confirmar="Remover a presença deste participante na atividade?"' : '' ?>>
                                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                        <input type="hidden" name="evento_id" value="<?= $eventoId ?>">
                                        <input type="hidden" name="atividade_id" value="<?= (int) $atividadeSelecionada['id'] ?>">
                                        <input type="hidden" name="participante_id" value="<?= (int) $participante['usuario_id'] ?>">
                                        <?php if ($participante['confirmado_em'] === null): ?>
                                            <input type="hidden" name="acao" value="registrar">
                                            <button class="management-button" type="submit">Registrar presença</button>
                                        <?php else: ?>
                                            <input type="hidden" name="acao" value="remover">
                                            <button class="management-button management-button--secondary" type="submit">Remover presença</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<script>
    (function () {
        var todos = document.getElementById('marcar-todos');
        var filtro = document.getElementById('filtro-participantes');
        var linhas = document.querySelectorAll('#tabela-presencas tbody tr[data-busca]');
        if (todos) {
            todos.addEventListener('change', function () {
                linhas.forEach(function (linha) {
                    var caixa = linha.querySelector('.presenca-pendente');
                    if (caixa && !linha.hidden) {
                        caixa.checked = todos.checked;
                    }
                });
            });
        }
        if (filtro) {
            filtro.addEventListener('input', function () {
                var termo = filtro.value.trim().toLowerCase();
                linhas.forEach(function (linha) {
                    linha.hidden = termo !== '' && linha.getAttribute('data-busca').indexOf(termo) === -1;
                });
            });
        }
        document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
            formulario.addEventListener('submit', function (evento) {
                if (!window.confirm(formulario.getAttribute('data-confirmar'))) {
                    evento.preventDefault();
                }
            });
        });
    }());
</script>
<?php renderManagementEnd($usuario); ?>
