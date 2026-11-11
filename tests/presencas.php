<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/presencas.php';

$organizadorId = null;
$outroOrganizadorId = null;
$participanteId = null;
$canceladoId = null;
$eventoId = null;
$outroEventoId = null;
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
        "INSERT INTO usuarios (nome, email, senha_hash, perfil)
         VALUES (:nome, :email, 'hash-de-teste', :perfil)"
    );
    foreach ([
        ['Organizador de teste', "presencas-org-{$nonce}@example.invalid", 'organizador'],
        ['Outro organizador', "presencas-outro-org-{$nonce}@example.invalid", 'organizador'],
        ['Participante de teste', "presencas-participante-{$nonce}@example.invalid", 'participante'],
        ['Participante cancelado', "presencas-cancelado-{$nonce}@example.invalid", 'participante'],
    ] as [$nome, $email, $perfil]) {
        $consulta->execute(['nome' => $nome, 'email' => $email, 'perfil' => $perfil]);
        $id = (int) $pdo->lastInsertId();
        if ($perfil === 'participante' && $participanteId === null) {
            $participanteId = $id;
        } elseif ($perfil === 'participante') {
            $canceladoId = $id;
        } elseif ($organizadorId === null) {
            $organizadorId = $id;
        } else {
            $outroOrganizadorId = $id;
        }
    }

    $consulta = $pdo->prepare(
        "INSERT INTO eventos
            (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Local de teste',
                 DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 20, 'encerrado')"
    );
    $consulta->execute(['titulo' => 'Teste de presença ' . $nonce]);
    $eventoId = (int) $pdo->lastInsertId();
    $consulta->execute(['titulo' => 'Outro evento ' . $nonce]);
    $outroEventoId = (int) $pdo->lastInsertId();

    $consulta = $pdo->prepare(
        'INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)'
    );
    $consulta->execute(['evento_id' => $eventoId, 'usuario_id' => $organizadorId]);
    $consulta->execute(['evento_id' => $outroEventoId, 'usuario_id' => $organizadorId]);

    $consulta = $pdo->prepare(
        'INSERT INTO espacos (evento_id, nome, capacidade) VALUES (:evento_id, :nome, 20)'
    );
    $consulta->execute(['evento_id' => $eventoId, 'nome' => 'Sala de teste']);
    $espacoId = (int) $pdo->lastInsertId();
    $consulta->execute(['evento_id' => $outroEventoId, 'nome' => 'Outra sala']);
    $outroEspacoId = (int) $pdo->lastInsertId();

    $consulta = $pdo->prepare(
        "INSERT INTO atividades (evento_id, espaco_id, titulo, tipo, inicio, fim, capacidade)
         VALUES (:evento_id, :espaco_id, :titulo, 'Palestra',
                 DATE_SUB(NOW(), INTERVAL 90 MINUTE), DATE_SUB(NOW(), INTERVAL 60 MINUTE), 20)"
    );
    $consulta->execute(['evento_id' => $eventoId, 'espaco_id' => $espacoId, 'titulo' => 'Atividade de teste']);
    $atividadeId = (int) $pdo->lastInsertId();
    $consulta->execute(['evento_id' => $outroEventoId, 'espaco_id' => $outroEspacoId, 'titulo' => 'Atividade de outro evento']);
    $outraAtividadeId = (int) $pdo->lastInsertId();

    $consulta = $pdo->prepare(
        'INSERT INTO inscricoes (usuario_id, evento_id, status)
         VALUES (:usuario_id, :evento_id, :status)'
    );
    $consulta->execute(['usuario_id' => $participanteId, 'evento_id' => $eventoId, 'status' => 'ativa']);
    $consulta->execute(['usuario_id' => $canceladoId, 'evento_id' => $eventoId, 'status' => 'cancelada']);

    registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $atividadeId, $participanteId, true);
    registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $atividadeId, $participanteId, true);
    $consulta = $pdo->prepare(
        'SELECT COUNT(*) FROM presencas_atividades
         WHERE atividade_id = :atividade_id AND usuario_id = :usuario_id'
    );
    $consulta->execute(['atividade_id' => $atividadeId, 'usuario_id' => $participanteId]);
    $assert((int) $consulta->fetchColumn() === 1, 'A presença repetida deveria permanecer única.');

    $historico = presencasAtividadesHistorico($pdo, $participanteId);
    $assert(
        count($historico) === 1 && $historico[0]['atividade_titulo'] === 'Atividade de teste',
        'A atividade confirmada não apareceu no histórico.'
    );

    registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $atividadeId, $participanteId, false);
    $consulta->execute(['atividade_id' => $atividadeId, 'usuario_id' => $participanteId]);
    $assert((int) $consulta->fetchColumn() === 0, 'A remoção da presença não foi aplicada.');

    $esperarRejeicao(
        static fn () => registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $atividadeId, $canceladoId, true),
        'inscrição Confirmada'
    );
    $esperarRejeicao(
        static fn () => registrarPresencaAtividade($pdo, $eventoId, $organizadorId, $outraAtividadeId, $participanteId, true),
        'Atividade não encontrada neste evento'
    );
    $esperarRejeicao(
        static fn () => registrarPresencaAtividade($pdo, $eventoId, $outroOrganizadorId, $atividadeId, $participanteId, true),
        'Atividade não encontrada neste evento'
    );

    echo "OK: {$assertions} verificações de presença por atividade e histórico.\n";
} finally {
    if ($eventoId !== null) {
        $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
        $consulta->execute(['id' => $eventoId]);
    }
    if ($outroEventoId !== null) {
        $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
        $consulta->execute(['id' => $outroEventoId]);
    }
    foreach ([$organizadorId, $outroOrganizadorId, $participanteId, $canceladoId] as $usuarioId) {
        if ($usuarioId !== null) {
            $consulta = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $consulta->execute(['id' => $usuarioId]);
        }
    }
}
