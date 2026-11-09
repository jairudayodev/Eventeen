<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/programacao.php';

$usuarioId = null;
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
    $email = 'teste-programacao-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $consulta = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil)
         VALUES ('Teste de programação', :email, 'hash-de-teste', 'organizador')"
    );
    $consulta->execute(['email' => $email]);
    $usuarioId = (int) $pdo->lastInsertId();

    $titulo = 'Teste de programação ' . bin2hex(random_bytes(4));
    $consulta = $pdo->prepare(
        "INSERT INTO eventos
            (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Local de teste',
                 DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 10 HOUR), 100, 'publicado')"
    );
    $consulta->execute(['titulo' => $titulo]);
    $eventoId = (int) $pdo->lastInsertId();
    $consulta = $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)');
    $consulta->execute(['evento_id' => $eventoId, 'usuario_id' => $usuarioId]);

    $tituloOutro = 'Teste de vínculo ' . bin2hex(random_bytes(4));
    $consulta = $pdo->prepare(
        "INSERT INTO eventos
            (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Local de teste',
                 DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 10 HOUR), 100, 'publicado')"
    );
    $consulta->execute(['titulo' => $tituloOutro]);
    $outroEventoId = (int) $pdo->lastInsertId();
    $consulta = $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)');
    $consulta->execute(['evento_id' => $outroEventoId, 'usuario_id' => $usuarioId]);

    salvarEspaco($pdo, $eventoId, $usuarioId, null, 'Sala A', 40, 'Projetor');
    salvarEspaco($pdo, $eventoId, $usuarioId, null, 'Sala B', 120, '');
    $consulta = $pdo->prepare('SELECT id FROM espacos WHERE evento_id = :evento_id ORDER BY id');
    $consulta->execute(['evento_id' => $eventoId]);
    [$salaA, $salaB] = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));

    salvarPalestrante($pdo, $eventoId, $usuarioId, null, 'Palestrante A', '', '');
    salvarPalestrante($pdo, $eventoId, $usuarioId, null, 'Palestrante B', '', '');
    $consulta = $pdo->prepare('SELECT id FROM palestrantes WHERE evento_id = :evento_id ORDER BY id');
    $consulta->execute(['evento_id' => $eventoId]);
    [$palestranteA, $palestranteB] = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    salvarEspaco($pdo, $outroEventoId, $usuarioId, null, 'Sala de outro evento', 100, '');
    $consulta = $pdo->prepare('SELECT id FROM espacos WHERE evento_id = :evento_id AND nome = :nome');
    $consulta->execute(['evento_id' => $outroEventoId, 'nome' => 'Sala de outro evento']);
    $salaOutroEvento = (int) $consulta->fetchColumn();
    salvarPalestrante($pdo, $outroEventoId, $usuarioId, null, 'Palestrante de outro evento', '', '');
    $consulta = $pdo->prepare('SELECT id FROM palestrantes WHERE evento_id = :evento_id AND nome = :nome');
    $consulta->execute(['evento_id' => $outroEventoId, 'nome' => 'Palestrante de outro evento']);
    $palestranteOutroEvento = (int) $consulta->fetchColumn();

    $inicio = (new DateTimeImmutable('+2 hours'))->format('Y-m-d\TH:i');
    $fim = (new DateTimeImmutable('+3 hours'))->format('Y-m-d\TH:i');
    salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Atividade A', '', 'Palestra', $inicio, $fim, $salaA, 30, [$palestranteA]);
    $consulta = $pdo->prepare('SELECT id FROM atividades WHERE evento_id = :evento_id');
    $consulta->execute(['evento_id' => $eventoId]);
    $atividadeId = (int) $consulta->fetchColumn();
    $assert($atividadeId > 0, 'A atividade válida não foi salva.');
    $inicioEvento = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
    $fimEvento = (new DateTimeImmutable('+10 hours'))->format('Y-m-d H:i:s');
    $esperarRejeicao(
        static fn () => validarLimitesAtividadesEvento($pdo, $eventoId, $inicioEvento, $fimEvento, 29),
        'capacidade do evento não pode ser menor'
    );
    $periodoInicioReduzido = (new DateTimeImmutable('+2 hours 30 minutes'))->format('Y-m-d H:i:s');
    $esperarRejeicao(
        static fn () => validarLimitesAtividadesEvento($pdo, $eventoId, $periodoInicioReduzido, $fimEvento, 100),
        'período do evento não pode excluir atividades'
    );

    $inicioAdjacente = $fim;
    $fimAdjacente = (new DateTimeImmutable('+4 hours'))->format('Y-m-d\TH:i');
    salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Atividade adjacente', '', 'Oficina', $inicioAdjacente, $fimAdjacente, $salaA, 30, [$palestranteA]);

    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Conflito de espaço', '', 'Palestra', $inicio, $fim, $salaA, 20, [$palestranteB]),
        'espaço já está reservado'
    );
    $inicioSobreposto = (new DateTimeImmutable('+2 hours 30 minutes'))->format('Y-m-d\TH:i');
    $fimSobreposto = (new DateTimeImmutable('+3 hours 30 minutes'))->format('Y-m-d\TH:i');
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Conflito de palestrante', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaB, 20, [$palestranteA]),
        'palestrantes já estão'
    );
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Capacidade acima do espaço', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaA, 41, []),
        'capacidade da atividade não pode superar a capacidade do espaço'
    );
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Capacidade acima do evento', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaB, 101, []),
        'capacidade da atividade não pode superar a capacidade do evento'
    );
    $foraDoEventoInicio = (new DateTimeImmutable('+10 hours'))->format('Y-m-d\TH:i');
    $foraDoEventoFim = (new DateTimeImmutable('+11 hours'))->format('Y-m-d\TH:i');
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Fora do evento', '', 'Palestra', $foraDoEventoInicio, $foraDoEventoFim, $salaB, 20, []),
        'dentro do período do evento'
    );
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Palestrante de outro evento', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaB, 20, [$palestranteOutroEvento]),
        'palestrantes cadastrados neste evento'
    );
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId, null, 'Espaço de outro evento', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaOutroEvento, 20, []),
        'espaço cadastrado para este evento'
    );
    $esperarRejeicao(
        static fn () => salvarEspaco($pdo, $eventoId, $usuarioId, $salaA, 'Sala A', 20, ''),
        'capacidade não pode ser menor'
    );
    $esperarRejeicao(
        static fn () => excluirEspaco($pdo, $eventoId, $usuarioId, $salaA),
        'atividades agendadas'
    );
    $esperarRejeicao(
        static fn () => salvarAtividade($pdo, $eventoId, $usuarioId + 999999, null, 'Sem permissão', '', 'Palestra', $inicioSobreposto, $fimSobreposto, $salaB, 20, []),
        'permissão para alterá-lo'
    );

    salvarAtividade($pdo, $eventoId, $usuarioId, $atividadeId, 'Atividade atualizada', '', 'Palestra', $inicio, $fim, $salaB, 25, [$palestranteB]);
    $consulta = $pdo->prepare('SELECT titulo, espaco_id FROM atividades WHERE id = :id');
    $consulta->execute(['id' => $atividadeId]);
    $atividade = $consulta->fetch();
    $assert($atividade['titulo'] === 'Atividade atualizada' && (int) $atividade['espaco_id'] === $salaB, 'A edição da atividade não foi aplicada.');

    excluirAtividade($pdo, $eventoId, $usuarioId, $atividadeId);
    $consulta = $pdo->prepare('SELECT COUNT(*) FROM atividades WHERE id = :id');
    $consulta->execute(['id' => $atividadeId]);
    $assert((int) $consulta->fetchColumn() === 0, 'A atividade não foi excluída.');

    echo "OK: {$assertions} verificações de programação, vínculos, capacidade e conflitos.\n";
} finally {
    if ($eventoId !== null) {
        $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
        $consulta->execute(['id' => $eventoId]);
    }
    if ($outroEventoId !== null) {
        $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :id');
        $consulta->execute(['id' => $outroEventoId]);
    }
    if ($usuarioId !== null) {
        $consulta = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $consulta->execute(['id' => $usuarioId]);
    }
}
