<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/inscricoes.php';

if (($argv[1] ?? '') === '--worker') {
    $usuarioId = (int) ($argv[2] ?? 0);
    $eventoId = (int) ($argv[3] ?? 0);
    $barreira = (string) ($argv[4] ?? '');
    file_put_contents($barreira . DIRECTORY_SEPARATOR . 'ready-' . $usuarioId, 'ready');
    $limite = microtime(true) + 15;
    while (!is_file($barreira . DIRECTORY_SEPARATOR . 'start')) {
        if (microtime(true) >= $limite) {
            fwrite(STDERR, "Tempo esgotado aguardando início concorrente.\n");
            exit(1);
        }
        usleep(10000);
    }

    try {
        inscreverParticipante($pdo, $usuarioId, $eventoId);
        echo "accepted";
    } catch (DomainException $exception) {
        echo "rejected: " . $exception->getMessage();
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$eventosDeTeste = [];
$usuariosDeTeste = [];
$barreira = null;
$assertions = 0;

$assert = static function (bool $condicao, string $mensagem) use (&$assertions): void {
    $assertions++;
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
};

$criarUsuario = static function () use ($pdo, &$usuariosDeTeste): int {
    $email = 'teste-inscricoes-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $consulta = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash) VALUES ('Teste de inscrição', :email, 'hash-de-teste')"
    );
    $consulta->execute(['email' => $email]);
    $id = (int) $pdo->lastInsertId();
    $usuariosDeTeste[] = $id;

    return $id;
};

$criarEvento = static function (string $titulo, ?int $capacidade, bool $iniciado = false) use ($pdo, &$eventosDeTeste): int {
    $inicio = $iniciado ? 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)' : 'DATE_ADD(NOW(), INTERVAL 1 DAY)';
    $consulta = $pdo->prepare(
        "INSERT INTO eventos
            (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Local de teste',
                 {$inicio}, DATE_ADD(NOW(), INTERVAL 2 DAY), :capacidade, 'publicado')"
    );
    $consulta->execute(['titulo' => $titulo, 'capacidade' => $capacidade]);
    $id = (int) $pdo->lastInsertId();
    $eventosDeTeste[] = $id;

    return $id;
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
    $usuarioDuplicado = $criarUsuario();
    $eventoDuplicado = $criarEvento('Teste de duplicidade ' . bin2hex(random_bytes(4)), 5);
    inscreverParticipante($pdo, $usuarioDuplicado, $eventoDuplicado);
    $esperarRejeicao(
        static fn () => inscreverParticipante($pdo, $usuarioDuplicado, $eventoDuplicado),
        'já está inscrito'
    );
    $consulta = $pdo->prepare(
        "SELECT COUNT(*) FROM inscricoes WHERE usuario_id = :usuario_id AND evento_id = :evento_id AND status = 'ativa'"
    );
    $consulta->execute(['usuario_id' => $usuarioDuplicado, 'evento_id' => $eventoDuplicado]);
    $assert((int) $consulta->fetchColumn() === 1, 'A duplicidade criou mais de uma inscrição ativa.');

    $usuarioHorario = $criarUsuario();
    $eventoIniciado = $criarEvento('Teste de horário ' . bin2hex(random_bytes(4)), 5, true);
    $esperarRejeicao(
        static fn () => inscreverParticipante($pdo, $usuarioHorario, $eventoIniciado),
        'não está mais aceitando'
    );

    $usuarioLotado = $criarUsuario();
    $usuarioProximo = $criarUsuario();
    $eventoLotado = $criarEvento('Teste de vagas ' . bin2hex(random_bytes(4)), 1);
    inscreverParticipante($pdo, $usuarioLotado, $eventoLotado);
    $esperarRejeicao(
        static fn () => inscreverParticipante($pdo, $usuarioProximo, $eventoLotado),
        'esgotadas'
    );
    $consulta = $pdo->prepare('SELECT id FROM inscricoes WHERE usuario_id = :usuario_id AND evento_id = :evento_id');
    $consulta->execute(['usuario_id' => $usuarioLotado, 'evento_id' => $eventoLotado]);
    $inscricaoId = (int) $consulta->fetchColumn();
    $assert(cancelarInscricao($pdo, $usuarioLotado, $inscricaoId), 'O cancelamento não liberou a inscrição ativa.');
    $assert(!cancelarInscricao($pdo, $usuarioLotado, $inscricaoId), 'Uma inscrição cancelada foi cancelada novamente.');
    inscreverParticipante($pdo, $usuarioProximo, $eventoLotado);
    $consulta = $pdo->prepare('SELECT id FROM inscricoes WHERE usuario_id = :usuario_id AND evento_id = :evento_id');
    $consulta->execute(['usuario_id' => $usuarioProximo, 'evento_id' => $eventoLotado]);
    $assert(cancelarInscricao($pdo, $usuarioProximo, (int) $consulta->fetchColumn()), 'Não foi possível liberar a vaga para reativação.');
    inscreverParticipante($pdo, $usuarioLotado, $eventoLotado);
    $consulta = $pdo->prepare('SELECT id FROM inscricoes WHERE usuario_id = :usuario_id AND evento_id = :evento_id');
    $consulta->execute(['usuario_id' => $usuarioLotado, 'evento_id' => $eventoLotado]);
    $assert((int) $consulta->fetchColumn() === $inscricaoId, 'A reativação criou uma inscrição duplicada.');
    $consulta = $pdo->prepare(
        "UPDATE eventos SET data_inicio = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id"
    );
    $consulta->execute(['id' => $eventoLotado]);
    $assert(
        !cancelarInscricao($pdo, $usuarioLotado, $inscricaoId),
        'Uma inscrição de evento iniciado foi cancelada.'
    );

    $eventoConcorrente = $criarEvento('Teste concorrente ' . bin2hex(random_bytes(4)), 2);
    $usuariosConcorrentes = [];
    for ($i = 0; $i < 8; $i++) {
        $usuariosConcorrentes[] = $criarUsuario();
    }
    $barreira = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eventeen-inscricoes-' . bin2hex(random_bytes(8));
    if (!mkdir($barreira)) {
        throw new RuntimeException('Não foi possível criar a barreira dos testes concorrentes.');
    }

    $processos = [];
    try {
        foreach ($usuariosConcorrentes as $usuarioId) {
            $pipes = [];
            $processo = proc_open(
                [PHP_BINARY, __FILE__, '--worker', (string) $usuarioId, (string) $eventoConcorrente, $barreira],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($processo)) {
                throw new RuntimeException('Não foi possível iniciar um processo concorrente.');
            }
            fclose($pipes[0]);
            $processos[] = ['processo' => $processo, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        }

        $limite = microtime(true) + 15;
        do {
            $prontos = count(glob($barreira . DIRECTORY_SEPARATOR . 'ready-*') ?: []);
            if ($prontos === count($usuariosConcorrentes)) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $limite);

        $assert($prontos === count($usuariosConcorrentes), 'Os processos concorrentes não ficaram prontos a tempo.');
        file_put_contents($barreira . DIRECTORY_SEPARATOR . 'start', 'start');

        $resultados = [];
        foreach ($processos as $item) {
            $resultado = stream_get_contents($item['stdout']);
            $erro = stream_get_contents($item['stderr']);
            fclose($item['stdout']);
            fclose($item['stderr']);
            $codigo = proc_close($item['processo']);
            $assert($codigo === 0, 'Um processo concorrente falhou: ' . $erro);
            $resultados[] = $resultado;
        }
    } finally {
        if (!is_file($barreira . DIRECTORY_SEPARATOR . 'start')) {
            file_put_contents($barreira . DIRECTORY_SEPARATOR . 'start', 'start');
        }
        foreach ($processos as $item) {
            if (is_resource($item['processo'])) {
                fclose($item['stdout']);
                fclose($item['stderr']);
                proc_terminate($item['processo']);
                proc_close($item['processo']);
            }
        }
        foreach (glob($barreira . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        rmdir($barreira);
        $barreira = null;
    }

    $aceitas = count(array_filter($resultados, static fn (string $resultado): bool => $resultado === 'accepted'));
    $recusadas = count(array_filter($resultados, static fn (string $resultado): bool => str_starts_with($resultado, 'rejected:')));
    $assert($aceitas === 2, 'A concorrência aceitou ' . $aceitas . ' inscrições, mas a capacidade é 2.');
    $assert($recusadas === 6, 'A concorrência rejeitou ' . $recusadas . ' inscrições, mas eram esperadas 6.');
    $consulta = $pdo->prepare(
        "SELECT COUNT(*) FROM inscricoes WHERE evento_id = :evento_id AND status = 'ativa'"
    );
    $consulta->execute(['evento_id' => $eventoConcorrente]);
    $assert((int) $consulta->fetchColumn() === 2, 'A capacidade foi excedida após as solicitações concorrentes.');

    echo "OK: {$assertions} verificações de inscrição, cancelamento e concorrência.\n";
} finally {
    if ($barreira !== null && is_dir($barreira)) {
        foreach (glob($barreira . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        rmdir($barreira);
    }
    foreach ($eventosDeTeste as $eventoId) {
        $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
        $consulta->execute(['id' => $eventoId]);
    }
    foreach ($usuariosDeTeste as $usuarioId) {
        $consulta = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $consulta->execute(['id' => $usuarioId]);
    }
}
