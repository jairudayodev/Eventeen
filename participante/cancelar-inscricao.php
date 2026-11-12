<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/inscricoes.php';
$usuario = requireRole('participante');
$inscricaoId = filter_var($_GET['id'] ?? $_POST['inscricao_id'] ?? null, FILTER_VALIDATE_INT);
if (!$inscricaoId || $inscricaoId < 1) {
    header('Location: minhas-inscricoes.php');
    exit;
}
$consulta = $pdo->prepare(
    "SELECT i.id, e.titulo, e.data_inicio
     FROM inscricoes i JOIN eventos e ON e.id = i.evento_id
     WHERE i.id = :id AND i.usuario_id = :usuario_id
       AND " . condicaoCancelamentoPermitido()
);
$consulta->execute(['id' => $inscricaoId, 'usuario_id' => (int) $usuario['id']]);
$inscricao = $consulta->fetch();
if (!$inscricao) {
    header('Location: minhas-inscricoes.php?cancelamento=indisponivel');
    exit;
}
$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if (cancelarInscricao($pdo, (int) $usuario['id'], $inscricaoId)) {
        header('Location: minhas-inscricoes.php?cancelamento=concluido');
        exit;
    }
    $erro = 'Não foi possível cancelar esta inscrição. Atualize a página e tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Cancelar inscrição | Eventeen</title>
    <link rel="stylesheet" href="<?= h(applicationBasePath()) ?>/css/style.css">
</head>
<body class="app-body">
    <div class="app-shell app-shell--compact">
        <?php appHeader($usuario); ?>
        <main class="app-content" id="conteudo">
            <section class="app-bottom-sheet" aria-label="Confirmar cancelamento">
                <h2 class="app-bottom-sheet__title">Cancelar inscrição?</h2>
                <p class="app-bottom-sheet__text">Ao confirmar, sua vaga será liberada para outros participantes e a inscrição será removida do seu histórico ativo.</p>
                <?php if ($erro !== null): ?><p class="app-notice app-notice--error" role="alert"><?= h($erro) ?></p><?php endif; ?>
                <div class="app-card">
                    <div class="app-stat-inline"><span class="app-stat-inline__label">Evento</span><span class="app-stat-inline__value"><?= h($inscricao['titulo']) ?></span></div>
                    <div class="app-stat-inline"><span class="app-stat-inline__label">Data</span><span class="app-stat-inline__value"><?= h(formatDateTime($inscricao['data_inicio'])) ?></span></div>
                </div>
                <div class="app-action-group">
                    <form method="post" action="cancelar-inscricao.php?id=<?= (int) $inscricaoId ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="inscricao_id" value="<?= (int) $inscricaoId ?>">
                        <button class="app-button" type="submit">Confirmar cancelamento</button>
                    </form>
                    <a class="app-button app-button--secondary" href="minhas-inscricoes.php">Voltar</a>
                </div>
            </section>
        </main>
        <?php appNavigation('inscricoes'); ?>
    </div>
</body>
</html>
