<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

validateCsrfToken();

encerrarSessao();

header('Location: ' . applicationRootPrefix() . 'index.php');
exit;
