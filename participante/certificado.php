<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/certificados.php';

$usuario = requireRole('participante');
$usuarioId = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $atividadeId = filter_var($_POST['atividade_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$atividadeId || $atividadeId < 1) {
        header('Location: historico-certificados.php');
        exit;
    }
    try {
        $codigo = emitirCertificadoAtividade($pdo, $usuarioId, $atividadeId);
    } catch (DomainException $exception) {
        $_SESSION['aviso_certificado'] = $exception->getMessage();
        header('Location: historico-certificados.php');
        exit;
    }
    header('Location: certificado.php?codigo=' . rawurlencode($codigo));
    exit;
}

$codigoRecebido = $_GET['codigo'] ?? '';
$certificado = is_string($codigoRecebido) ? buscarCertificadoPorCodigo($pdo, $codigoRecebido) : null;
if ($certificado === null || (int) $certificado['usuario_id'] !== $usuarioId) {
    http_response_code(404);
    exit('Certificado não encontrado.');
}

$urlValidacao = applicationBaseUrl() . '/validar-certificado.php';
$pdf = gerarPdfCertificado($certificado, $urlValidacao);
$nomeArquivo = 'certificado-' . strtolower((string) $certificado['codigo_validacao']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
