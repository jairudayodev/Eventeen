<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/presencas.php';
require __DIR__ . '/../includes/certificados.php';
require __DIR__ . '/../includes/relatorios.php';

$usuarios = [];
$eventoId = null;
$assertions = 0;
$assert = static function (bool $condicao, string $mensagem) use (&$assertions): void {
    $assertions++;
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
};
$esperarRejeicao = static function (callable $acao, string $mensagem) use ($assert): void {
    try {
        $acao();
    } catch (DomainException $exception) {
        $assert(str_contains($exception->getMessage(), $mensagem), 'Motivo inesperado: ' . $exception->getMessage());
        return;
    }
    $assert(false, 'A operação deveria ter sido rejeitada: ' . $mensagem);
};

try {
    $nonce = bin2hex(random_bytes(6));
    $consulta = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, 'hash-de-teste', :perfil)"
    );
    foreach ([
        'organizador' => ['Organizadora de teste', 'organizador'],
        'presente' => ['Participante Presente', 'participante'],
        'ausente' => ['Participante Ausente', 'participante'],
    ] as $chave => [$nome, $perfil]) {
        $consulta->execute(['nome' => $nome, 'email' => "certificados-{$chave}-{$nonce}@example.invalid", 'perfil' => $perfil]);
        $usuarios[$chave] = (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        "INSERT INTO eventos (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Auditório', DATE_SUB(NOW(), INTERVAL 3 HOUR),
                 DATE_ADD(NOW(), INTERVAL 3 HOUR), 30, 'publicado')"
    )->execute(['titulo' => 'Semana de Certificação ' . $nonce]);
    $eventoId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)')
        ->execute(['evento_id' => $eventoId, 'usuario_id' => $usuarios['organizador']]);
    $pdo->prepare("INSERT INTO espacos (evento_id, nome, capacidade) VALUES (:evento_id, 'Laboratório', 30)")
        ->execute(['evento_id' => $eventoId]);
    $espacoId = (int) $pdo->lastInsertId();

    $inserirAtividade = $pdo->prepare(
        "INSERT INTO atividades (evento_id, espaco_id, titulo, tipo, inicio, fim, capacidade)
         VALUES (:evento_id, :espaco_id, :titulo, 'Oficina', :inicio, :fim, 30)"
    );
    $inserirAtividade->execute([
        'evento_id' => $eventoId, 'espaco_id' => $espacoId, 'titulo' => 'Oficina de Introdução à Programação',
        'inicio' => date('Y-m-d H:i:00', strtotime('-150 minutes')), 'fim' => date('Y-m-d H:i:00', strtotime('-60 minutes')),
    ]);
    $atividadeConcluida = (int) $pdo->lastInsertId();
    $inserirAtividade->execute([
        'evento_id' => $eventoId, 'espaco_id' => $espacoId, 'titulo' => 'Atividade em andamento',
        'inicio' => date('Y-m-d H:i:00', strtotime('-30 minutes')), 'fim' => date('Y-m-d H:i:00', strtotime('+60 minutes')),
    ]);
    $atividadeEmAndamento = (int) $pdo->lastInsertId();

    $inscrever = $pdo->prepare("INSERT INTO inscricoes (usuario_id, evento_id, status) VALUES (:usuario_id, :evento_id, 'ativa')");
    $inscrever->execute(['usuario_id' => $usuarios['presente'], 'evento_id' => $eventoId]);
    $inscrever->execute(['usuario_id' => $usuarios['ausente'], 'evento_id' => $eventoId]);

    registrarPresencaAtividade($pdo, $eventoId, $usuarios['organizador'], $atividadeConcluida, $usuarios['presente'], true);
    registrarPresencaAtividade($pdo, $eventoId, $usuarios['organizador'], $atividadeEmAndamento, $usuarios['presente'], true);

    // RN09: sem presença confirmada não há certificado.
    $esperarRejeicao(
        static fn () => emitirCertificadoAtividade($pdo, $usuarios['ausente'], $atividadeConcluida),
        'presença confirmada'
    );
    // O certificado só é liberado após o término da atividade.
    $esperarRejeicao(
        static fn () => emitirCertificadoAtividade($pdo, $usuarios['presente'], $atividadeEmAndamento),
        'após o término'
    );

    $codigo = emitirCertificadoAtividade($pdo, $usuarios['presente'], $atividadeConcluida);
    $assert(preg_match('/^[0-9A-F]{16}$/', $codigo) === 1, 'O código de validação não tem o formato esperado.');
    $assert(
        emitirCertificadoAtividade($pdo, $usuarios['presente'], $atividadeConcluida) === $codigo,
        'Uma segunda solicitação deveria reapresentar o mesmo certificado.'
    );
    $consulta = $pdo->prepare('SELECT COUNT(*), MAX(carga_horaria) FROM certificados WHERE usuario_id = :usuario_id');
    $consulta->execute(['usuario_id' => $usuarios['presente']]);
    [$total, $carga] = $consulta->fetch(PDO::FETCH_NUM);
    $assert((int) $total === 1, 'Foi criado mais de um certificado para a mesma presença.');
    $assert((float) $carga === 1.5, 'A carga horária deveria ser 1,5 hora; obtida: ' . $carga);

    $certificado = buscarCertificadoPorCodigo($pdo, formatarCodigoCertificado($codigo));
    $assert($certificado !== null && $certificado['participante_nome'] === 'Participante Presente', 'O código não identificou o certificado.');
    $assert(buscarCertificadoPorCodigo($pdo, 'FFFF-FFFF-FFFF-FFFF') === null || $codigo === 'FFFFFFFFFFFFFFFF', 'Um código inexistente foi aceito.');

    $pdf = gerarPdfCertificado($certificado, 'http://localhost/Eventeen/validar-certificado.php');
    $assert(str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, "%%EOF"), 'O PDF gerado não tem cabeçalho e final válidos.');
    $assert(str_contains($pdf, mb_convert_encoding('PARTICIPAÇÃO', 'Windows-1252', 'UTF-8')), 'O PDF não contém o título do certificado.');
    $assert(str_contains($pdf, formatarCodigoCertificado($codigo)), 'O PDF não contém o código de validação.');
    preg_match('/startxref\n(\d+)\n/', $pdf, $xref);
    $assert(isset($xref[1]) && substr($pdf, (int) $xref[1], 4) === 'xref', 'A tabela xref do PDF está em posição inválida.');

    $esperarRejeicao(
        static fn () => registrarPresencaAtividade($pdo, $eventoId, $usuarios['organizador'], $atividadeConcluida, $usuarios['presente'], false),
        'certificado já foi emitido'
    );

    $historico = historicoAtividadesParticipante($pdo, $usuarios['presente']);
    $assert(count($historico) === 1 && $historico[0]['codigo_validacao'] === $codigo, 'O histórico não exibiu o certificado emitido.');

    if (getenv('EVENTEEN_SALVAR_PDF')) {
        file_put_contents((string) getenv('EVENTEEN_SALVAR_PDF'), $pdf);
    }

    echo "OK: {$assertions} verificações de certificados, código de validação e PDF.\n";
} finally {
    if ($eventoId !== null) {
        $pdo->prepare('DELETE FROM certificados WHERE evento_id = :id')->execute(['id' => $eventoId]);
        $pdo->prepare('DELETE FROM eventos WHERE id = :id')->execute(['id' => $eventoId]);
    }
    foreach ($usuarios as $usuarioId) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $usuarioId]);
    }
}
