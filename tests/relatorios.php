<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/relatorios.php';

$usuarios = [];
$eventos = [];
$assertions = 0;
$assert = static function (bool $condicao, string $mensagem) use (&$assertions): void {
    $assertions++;
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
};

try {
    $nonce = bin2hex(random_bytes(6));
    $inserirUsuario = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, 'hash-de-teste', :perfil)"
    );
    $inserirUsuario->execute(['nome' => 'Organizador', 'email' => "relatorios-org-{$nonce}@example.invalid", 'perfil' => 'organizador']);
    $organizadorId = (int) $pdo->lastInsertId();
    $usuarios[] = $organizadorId;
    $inserirUsuario->execute(['nome' => 'Outro organizador', 'email' => "relatorios-outro-{$nonce}@example.invalid", 'perfil' => 'organizador']);
    $outroOrganizadorId = (int) $pdo->lastInsertId();
    $usuarios[] = $outroOrganizadorId;
    $participantes = [];
    for ($i = 1; $i <= 4; $i++) {
        $inserirUsuario->execute(['nome' => "Participante {$i}", 'email' => "relatorios-p{$i}-{$nonce}@example.invalid", 'perfil' => 'participante']);
        $participantes[$i] = (int) $pdo->lastInsertId();
        $usuarios[] = $participantes[$i];
    }

    $inserirEvento = $pdo->prepare(
        "INSERT INTO eventos (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Auditório', :inicio, :fim, 10, 'encerrado')"
    );
    $inserirEvento->execute(['titulo' => 'Relatório A ' . $nonce, 'inicio' => '2026-03-10 08:00:00', 'fim' => '2026-03-10 18:00:00']);
    $eventoA = (int) $pdo->lastInsertId();
    $inserirEvento->execute(['titulo' => 'Relatório B ' . $nonce, 'inicio' => '2026-05-20 08:00:00', 'fim' => '2026-05-20 18:00:00']);
    $eventoB = (int) $pdo->lastInsertId();
    $eventos = [$eventoA, $eventoB];
    $vincular = $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)');
    $vincular->execute(['evento_id' => $eventoA, 'usuario_id' => $organizadorId]);
    $vincular->execute(['evento_id' => $eventoB, 'usuario_id' => $organizadorId]);

    $pdo->prepare("INSERT INTO espacos (evento_id, nome, capacidade) VALUES (:evento_id, 'Sala', 10)")->execute(['evento_id' => $eventoA]);
    $espacoA = (int) $pdo->lastInsertId();
    $inserirAtividade = $pdo->prepare(
        "INSERT INTO atividades (evento_id, espaco_id, titulo, tipo, inicio, fim, capacidade)
         VALUES (:evento_id, :espaco_id, :titulo, 'Palestra', :inicio, :fim, 10)"
    );
    $inserirAtividade->execute(['evento_id' => $eventoA, 'espaco_id' => $espacoA, 'titulo' => 'Abertura', 'inicio' => '2026-03-10 08:00:00', 'fim' => '2026-03-10 10:00:00']);
    $atividade1 = (int) $pdo->lastInsertId();
    $inserirAtividade->execute(['evento_id' => $eventoA, 'espaco_id' => $espacoA, 'titulo' => 'Encerramento', 'inicio' => '2026-03-10 16:00:00', 'fim' => '2026-03-10 18:00:00']);
    $atividade2 = (int) $pdo->lastInsertId();

    // Massa do CA-RF13: três inscrições Confirmadas, uma Cancelada e duas presenças na atividade.
    $inscrever = $pdo->prepare('INSERT INTO inscricoes (usuario_id, evento_id, status) VALUES (:usuario_id, :evento_id, :status)');
    foreach ([1 => 'ativa', 2 => 'ativa', 3 => 'ativa', 4 => 'cancelada'] as $i => $status) {
        $inscrever->execute(['usuario_id' => $participantes[$i], 'evento_id' => $eventoA, 'status' => $status]);
    }
    $presenca = $pdo->prepare(
        'INSERT INTO presencas_atividades (atividade_id, evento_id, usuario_id) VALUES (:atividade_id, :evento_id, :usuario_id)'
    );
    $presenca->execute(['atividade_id' => $atividade1, 'evento_id' => $eventoA, 'usuario_id' => $participantes[1]]);
    $presenca->execute(['atividade_id' => $atividade1, 'evento_id' => $eventoA, 'usuario_id' => $participantes[2]]);
    // O participante 1 também esteve na segunda atividade: não pode ser contado duas vezes no evento.
    $presenca->execute(['atividade_id' => $atividade2, 'evento_id' => $eventoA, 'usuario_id' => $participantes[1]]);

    $porEvento = relatorioPorEvento($pdo, $organizadorId, ['evento_id' => $eventoA, 'de' => null, 'ate' => null]);
    $assert(count($porEvento) === 1, 'O filtro por evento deveria retornar um único evento.');
    $assert((int) $porEvento[0]['confirmadas'] === 3, 'Eram esperadas 3 inscrições confirmadas.');
    $assert((int) $porEvento[0]['canceladas'] === 1, 'Era esperada 1 inscrição cancelada.');
    $assert((int) $porEvento[0]['presentes'] === 2, 'Eram esperados 2 participantes distintos presentes.');
    $assert($porEvento[0]['taxa_presenca'] === 67, 'A taxa de presença deveria ser 67%.');

    $porAtividade = relatorioPorAtividade($pdo, $organizadorId, ['evento_id' => $eventoA, 'de' => null, 'ate' => null]);
    $presencasPorAtividade = array_column($porAtividade, 'presencas', 'atividade_titulo');
    $assert((int) $presencasPorAtividade['Abertura'] === 2, 'A atividade de abertura deveria ter 2 presenças.');
    $assert((int) $presencasPorAtividade['Encerramento'] === 1, 'A atividade de encerramento deveria ter 1 presença.');

    $periodo = relatorioPorEvento($pdo, $organizadorId, relatorioFiltros(['de' => '2026-05-01', 'ate' => '2026-05-31']));
    $assert(count($periodo) === 1 && (int) $periodo[0]['id'] === $eventoB, 'O filtro de período não isolou o evento de maio.');
    $assert(relatorioPorEvento($pdo, $outroOrganizadorId) === [], 'Um organizador sem vínculo visualizou eventos alheios.');

    $resumo = relatorioResumo($porEvento);
    $assert($resumo['confirmadas'] === 3 && $resumo['canceladas'] === 1 && $resumo['presentes'] === 2, 'O resumo consolidado está incorreto.');

    echo "OK: {$assertions} verificações de relatórios por evento, atividade e período.\n";
} finally {
    foreach ($eventos as $eventoId) {
        $pdo->prepare('DELETE FROM eventos WHERE id = :id')->execute(['id' => $eventoId]);
    }
    foreach ($usuarios as $usuarioId) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $usuarioId]);
    }
}
