<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/financeiro.php';

$usuarios = [];
$eventos = [];
$parceiroId = null;
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
    $inserirUsuario = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, 'hash-de-teste', :perfil)"
    );
    foreach (['organizador' => 'organizador', 'intruso' => 'organizador', 'admin' => 'administrador', 'p1' => 'participante', 'p2' => 'participante', 'p3' => 'participante'] as $chave => $perfil) {
        $inserirUsuario->execute(['nome' => 'Usuário ' . $chave, 'email' => "pagamentos-{$chave}-{$nonce}@example.invalid", 'perfil' => $perfil]);
        $usuarios[$chave] = (int) $pdo->lastInsertId();
    }
    $org = $usuarios['organizador'];

    $inserirEvento = $pdo->prepare(
        "INSERT INTO eventos (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, valor_inscricao, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Sala', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 11 DAY), 10, :valor, 'publicado')"
    );
    $inserirEvento->execute(['titulo' => 'Evento pago ' . $nonce, 'valor' => '50.00']);
    $eventoPago = (int) $pdo->lastInsertId();
    $inserirEvento->execute(['titulo' => 'Evento gratuito ' . $nonce, 'valor' => '0.00']);
    $eventoGratuito = (int) $pdo->lastInsertId();
    $eventos = [$eventoPago, $eventoGratuito];
    $vincular = $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)');
    $vincular->execute(['evento_id' => $eventoPago, 'usuario_id' => $org]);
    $vincular->execute(['evento_id' => $eventoGratuito, 'usuario_id' => $org]);

    $inscrever = $pdo->prepare('INSERT INTO inscricoes (usuario_id, evento_id, status) VALUES (:usuario_id, :evento_id, :status)');
    $inscricao = static function (string $chave, int $evento, string $status = 'ativa') use ($pdo, $inscrever, $usuarios): int {
        $inscrever->execute(['usuario_id' => $usuarios[$chave], 'evento_id' => $evento, 'status' => $status]);
        return (int) $pdo->lastInsertId();
    };
    $inscricao1 = $inscricao('p1', $eventoPago);
    $inscricao2 = $inscricao('p2', $eventoPago);
    $inscricaoCancelada = $inscricao('p3', $eventoPago, 'cancelada');
    $inscricaoGratuita = $inscricao('p1', $eventoGratuito);
    $hoje = date('Y-m-d');

    // RN12: pagamento registrado, sem duplicidade, por organizador do evento.
    registrarPagamentoInscricao($pdo, $org, $inscricao1, 5000, 'pix', 'confirmado', $hoje);
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $org, $inscricao1, 5000, 'pix', 'confirmado', $hoje), 'já possui um pagamento');
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $org, $inscricaoGratuita, 5000, 'pix', 'confirmado', $hoje), 'gratuito');
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $org, $inscricaoCancelada, 5000, 'pix', 'confirmado', $hoje), 'inscrições Confirmadas');
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $usuarios['intruso'], $inscricao2, 5000, 'pix', 'confirmado', $hoje), 'permissão');
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $org, $inscricao2, 5000, 'pix', 'confirmado', null), 'data do pagamento');
    $esperarRejeicao(static fn () => registrarPagamentoInscricao($pdo, $org, $inscricao2, 5000, 'cheque', 'pendente', null), 'forma de pagamento');

    $pagamentoPendente = registrarPagamentoInscricao($pdo, $org, $inscricao2, 5000, 'dinheiro', 'pendente', null);
    $resumo = resumoFinanceiroEvento($pdo, $eventoPago);
    $assert($resumo['saldo_centavos'] === 5000, 'O saldo deveria considerar apenas o pagamento confirmado.');
    $assert((float) $resumo['inscricoes_pendentes'] === 50.0, 'O pagamento pendente não foi totalizado.');

    atualizarPagamentoInscricao($pdo, $usuarios['admin'], $pagamentoPendente, 'confirmar', $hoje);
    $assert(resumoFinanceiroEvento($pdo, $eventoPago)['saldo_centavos'] === 10000, 'A confirmação não atualizou o saldo.');

    // Estorno em até 7 dias após o pagamento.
    $consulta = $pdo->prepare('SELECT id FROM pagamentos_inscricoes WHERE inscricao_id = :inscricao_id');
    $consulta->execute(['inscricao_id' => $inscricao1]);
    $pagamentoConfirmado = (int) $consulta->fetchColumn();
    $pdo->prepare('UPDATE pagamentos_inscricoes SET data_pagamento = DATE_SUB(CURDATE(), INTERVAL 10 DAY) WHERE id = :id')
        ->execute(['id' => $pagamentoConfirmado]);
    $esperarRejeicao(static fn () => atualizarPagamentoInscricao($pdo, $org, $pagamentoConfirmado, 'estornar', $hoje), 'prazo de 7 dias');
    $pdo->prepare('UPDATE pagamentos_inscricoes SET data_pagamento = DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE id = :id')
        ->execute(['id' => $pagamentoConfirmado]);
    atualizarPagamentoInscricao($pdo, $org, $pagamentoConfirmado, 'estornar', $hoje);
    $esperarRejeicao(static fn () => atualizarPagamentoInscricao($pdo, $org, $pagamentoConfirmado, 'estornar', $hoje), 'Somente pagamentos confirmados');
    $assert(resumoFinanceiroEvento($pdo, $eventoPago)['saldo_centavos'] === 5000, 'O estorno não foi descontado do saldo.');
    $esperarRejeicao(
        static fn () => atualizarPagamentoInscricao($pdo, $org, $pagamentoPendente, 'confirmar', date('Y-m-d', strtotime('+1 day'))),
        'futuro'
    );

    // RN13: tipos de apoio.
    $parceiroId = salvarParceiro($pdo, $org, 'Parceiro de teste ' . $nonce, '', '');
    $esperarRejeicao(static fn () => salvarPatrocinio($pdo, $eventoPago, $org, $parceiroId, 'Apoio', '', 0, 'financeiro'), 'maior que zero');
    $esperarRejeicao(static fn () => salvarPatrocinio($pdo, $eventoPago, $org, $parceiroId, 'Apoio', '', 0, 'material', ''), 'Descreva o recurso');
    $esperarRejeicao(static fn () => salvarPatrocinio($pdo, $eventoPago, $org, $parceiroId, 'Apoio', '', 1000, 'institucional', 'Divulgação'), 'não possuem valor');
    $material = salvarPatrocinio($pdo, $eventoPago, $org, $parceiroId, 'Apoio', 'Logo no banner', 0, 'material', '100 camisetas');
    $consulta = $pdo->prepare('SELECT tipo_apoio, valor, descricao_apoio FROM patrocinios WHERE id = :id');
    $consulta->execute(['id' => $material]);
    $linha = $consulta->fetch();
    $assert($linha['tipo_apoio'] === 'material' && $linha['valor'] === null && $linha['descricao_apoio'] === '100 camisetas', 'O apoio material não foi gravado corretamente.');
    atualizarStatusPatrocinio($pdo, $material, $usuarios['admin'], 'confirmado');
    $esperarRejeicao(
        static fn () => salvarLancamentoFinanceiro($pdo, $usuarios['admin'], 'Pagamento indevido', 'entrada', 1000, $hoje, 'realizado', $eventoPago, [], $material),
        'deve ser financeiro'
    );

    echo "OK: {$assertions} verificações de pagamentos de inscrição, estornos e tipos de apoio.\n";
} finally {
    foreach ($eventos as $eventoId) {
        $pdo->prepare(
            'DELETE pg FROM pagamentos_inscricoes pg JOIN inscricoes i ON i.id = pg.inscricao_id WHERE i.evento_id = :id'
        )->execute(['id' => $eventoId]);
        $pdo->prepare('DELETE FROM patrocinios WHERE evento_id = :id')->execute(['id' => $eventoId]);
        $pdo->prepare('DELETE FROM eventos WHERE id = :id')->execute(['id' => $eventoId]);
    }
    if ($parceiroId !== null) {
        $pdo->prepare('DELETE FROM parceiros WHERE id = :id')->execute(['id' => $parceiroId]);
    }
    foreach ($usuarios as $usuarioId) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $usuarioId]);
    }
}
