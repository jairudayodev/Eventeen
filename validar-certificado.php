<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/certificados.php';

$codigoRecebido = $_GET['codigo'] ?? '';
$codigo = is_string($codigoRecebido) ? trim($codigoRecebido) : '';
$certificado = $codigo !== '' ? buscarCertificadoPorCodigo($pdo, $codigo) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c0172d">
    <title>Validar certificado | Eventeen</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="auth-page">
        <a class="marca" href="index.php" aria-label="Eventeen - página inicial">
            <img class="logo" src="img/logo-eventeen.png" alt="Eventeen">
        </a>

        <section class="auth-card" aria-labelledby="titulo">
            <header class="auth-header">
                <p class="eyebrow">Fatec Ourinhos</p>
                <h1 id="titulo">Validar certificado</h1>
                <p class="subtitulo">Informe o código impresso no certificado.</p>
            </header>

            <?php if ($codigo !== '' && $certificado === null): ?>
                <p class="form-notice form-notice--error" role="alert">Nenhum certificado foi encontrado com este código.</p>
            <?php elseif ($certificado !== null): ?>
                <div class="form-notice" role="status">
                    <strong>Certificado válido.</strong><br>
                    Participante: <?= h($certificado['participante_nome']) ?><br>
                    Atividade: <?= h($certificado['atividade_titulo']) ?> (<?= h($certificado['atividade_tipo']) ?>)<br>
                    Evento: <?= h($certificado['evento_titulo']) ?><br>
                    Realização: <?= h(date('d/m/Y H:i', strtotime((string) $certificado['inicio']))) ?>–<?= h(date('H:i', strtotime((string) $certificado['fim']))) ?><br>
                    Carga horária: <?= h(formatarCargaHoraria((string) $certificado['carga_horaria'])) ?><br>
                    Emitido em: <?= h(date('d/m/Y', strtotime((string) $certificado['emitido_em']))) ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="validar-certificado.php" method="get">
                <?php renderRequiredFieldsNote(); ?>
                <div class="campo">
                    <label for="codigo">Código de validação <span class="campo-obrigatorio" aria-hidden="true">*</span></label>
                    <input type="text" id="codigo" name="codigo" value="<?= h($codigo) ?>" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="24" autocomplete="off" required>
                </div>
                <button type="submit" class="botao">Validar</button>
            </form>

            <p class="rodape"><a href="index.php">Voltar ao início</a></p>
        </section>
    </main>
</body>
</html>
