<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/financeiro.php';

$consultaTabelas = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('parceiros', 'patrocinios', 'financeiro_lancamentos', 'financeiro_rateios')"
);
if ((int) $consultaTabelas->fetchColumn() !== 4) {
    fwrite(STDERR, "Atualize o banco com banco.sql antes de executar os testes financeiros.\n");
    exit(2);
}

$usuarioId = null;
$outroUsuarioId = null;
$eventoA = null;
$eventoB = null;
$eventoAlheio = null;
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
        $assert(
            stripos($exception->getMessage(), $mensagem) !== false,
            'Motivo inesperado: ' . $exception->getMessage()
        );
        return;
    }
    $assert(false, 'A operação deveria ter sido rejeitada: ' . $mensagem);
};

try {
    $assert(financeiroCentavos('1.234,56') === 123456, 'O formato monetário brasileiro não foi interpretado corretamente.');
    $assert(financeiroCentavos('10.5') === 1050, 'O formato decimal com ponto não foi interpretado corretamente.');
    $esperarRejeicao(static fn () => financeiroCentavos('1,234.56'), 'formato');

    $email = 'teste-financeiro-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $consulta = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil)
         VALUES ('Teste financeiro', :email, 'hash-de-teste', 'organizador')"
    );
    $consulta->execute(['email' => $email]);
    $usuarioId = (int) $pdo->lastInsertId();

    $emailOutro = 'teste-financeiro-outro-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $consulta->execute(['email' => $emailOutro]);
    $outroUsuarioId = (int) $pdo->lastInsertId();

    $criarEvento = static function (string $titulo) use ($pdo): int {
        $consulta = $pdo->prepare(
            "INSERT INTO eventos
                (titulo, descricao, categoria, local, data_inicio, data_fim, status)
             VALUES (:titulo, 'Evento de teste financeiro', 'Teste', 'Local de teste',
                     DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 'publicado')"
        );
        $consulta->execute(['titulo' => $titulo]);
        return (int) $pdo->lastInsertId();
    };
    $eventoA = $criarEvento('Teste financeiro A ' . bin2hex(random_bytes(3)));
    $eventoB = $criarEvento('Teste financeiro B ' . bin2hex(random_bytes(3)));
    $eventoAlheio = $criarEvento('Teste financeiro alheio ' . bin2hex(random_bytes(3)));
    $vinculo = $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)');
    foreach ([$eventoA, $eventoB] as $idEvento) {
        $vinculo->execute(['evento_id' => $idEvento, 'usuario_id' => $usuarioId]);
    }
    $vinculo->execute(['evento_id' => $eventoAlheio, 'usuario_id' => $outroUsuarioId]);

    $emailParceiro = 'parceiro-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $parceiroId = salvarParceiro($pdo, $usuarioId, 'Parceiro teste', $emailParceiro, '');
    $esperarRejeicao(
        static fn () => salvarParceiro($pdo, $usuarioId, 'Parceiro duplicado', $emailParceiro, ''),
        'Já existe um parceiro'
    );
    $parceiroAlheio = salvarParceiro($pdo, $outroUsuarioId, 'Parceiro alheio', '', '');
    $esperarRejeicao(
        static fn () => salvarPatrocinio($pdo, $eventoA, $usuarioId, $parceiroAlheio, 'Ouro', '', 100000),
        'Parceiro não encontrado'
    );
    $patrocinioId = salvarPatrocinio($pdo, $eventoA, $usuarioId, $parceiroId, 'Ouro', 'Logo e estande', 100000);
    atualizarStatusPatrocinio($pdo, $patrocinioId, $usuarioId, 'confirmado');

    $entradaRecebida = salvarLancamentoFinanceiro(
        $pdo,
        $usuarioId,
        'Parcela recebida',
        'entrada',
        40000,
        date('Y-m-d'),
        'realizado',
        $eventoA,
        [],
        $patrocinioId
    );
    salvarLancamentoFinanceiro(
        $pdo,
        $usuarioId,
        'Parcela a receber',
        'entrada',
        60000,
        date('Y-m-d'),
        'pendente',
        $eventoA,
        [],
        $patrocinioId
    );
    $esperarRejeicao(
        static fn () => salvarLancamentoFinanceiro(
            $pdo,
            $usuarioId,
            'Pagamento excedente',
            'entrada',
            1,
            date('Y-m-d'),
            'pendente',
            $eventoA,
            [],
            $patrocinioId
        ),
        'ultrapassam o valor'
    );

    $esperarRejeicao(
        static fn () => salvarLancamentoFinanceiro(
            $pdo,
            $usuarioId,
            'Rateio incorreto',
            'despesa',
            10000,
            date('Y-m-d'),
            'realizado',
            $eventoA,
            [$eventoA => '60,00', $eventoB => '30,00'],
            null
        ),
        'soma dos rateios'
    );
    $despesaId = salvarLancamentoFinanceiro(
        $pdo,
        $usuarioId,
        'Despesa compartilhada',
        'despesa',
        10000,
        date('Y-m-d'),
        'realizado',
        $eventoA,
        [$eventoA => '60,00', $eventoB => '40,00'],
        null
    );
    $despesaPendenteId = salvarLancamentoFinanceiro(
        $pdo,
        $usuarioId,
        'Despesa aguardando pagamento',
        'despesa',
        2000,
        date('Y-m-d'),
        'pendente',
        $eventoA,
        [],
        null
    );
    atualizarStatusLancamento($pdo, $despesaPendenteId, $usuarioId, 'realizado');
    $esperarRejeicao(
        static fn () => salvarLancamentoFinanceiro(
            $pdo,
            $usuarioId,
            'Evento sem permissão',
            'despesa',
            10000,
            date('Y-m-d'),
            'realizado',
            $eventoA,
            [$eventoAlheio => '100,00'],
            null
        ),
        'não estão sob sua organização'
    );
    $esperarRejeicao(
        static fn () => atualizarStatusLancamento($pdo, $entradaRecebida, $outroUsuarioId, 'cancelado'),
        'não tem permissão'
    );
    $esperarRejeicao(
        static fn () => atualizarStatusPatrocinio($pdo, $patrocinioId, $usuarioId, 'cancelado'),
        'Cancele os pagamentos vinculados'
    );

    $resumoA = resumoFinanceiroEvento($pdo, $eventoA);
    $resumoB = resumoFinanceiroEvento($pdo, $eventoB);
    $assert((float) $resumoA['entradas'] === 400.0, 'Entradas realizadas do evento A incorretas.');
    $assert((float) $resumoA['despesas'] === 80.0, 'Despesas do evento A incorretas.');
    $assert($resumoA['saldo_centavos'] === 32000, 'Saldo do evento A incorreto.');
    $assert((float) $resumoA['pendente'] === 600.0, 'Pendências do evento A incorretas.');
    $assert((float) $resumoB['entradas'] === 0.0, 'O evento B recebeu uma entrada indevida.');
    $assert((float) $resumoB['despesas'] === 40.0, 'Despesas rateadas do evento B incorretas.');
    $assert($resumoB['saldo_centavos'] === -4000, 'Saldo negativo do evento B incorreto.');
    $assert(financeiroMoedaCentavos(-5) === '-R$ 0,05', 'O saldo negativo em centavos não foi formatado corretamente.');
    $assert(count(listarLancamentosEvento($pdo, $eventoA)) === 4, 'A consulta das movimentações do evento A está incorreta.');
    $assert($despesaId > 0, 'A despesa rateada não foi criada.');

    echo "Testes financeiros concluídos: {$assertions} verificações.\n";
} finally {
    if ($usuarioId !== null) {
        $consulta = $pdo->prepare('DELETE FROM financeiro_lancamentos WHERE criado_por IN (:usuario_id, :outro_usuario_id)');
        $consulta->execute([
            'usuario_id' => $usuarioId,
            'outro_usuario_id' => $outroUsuarioId ?? $usuarioId,
        ]);
    }
    if ($eventoA !== null || $eventoB !== null || $eventoAlheio !== null) {
        $eventosCriados = array_values(array_filter([$eventoA, $eventoB, $eventoAlheio], static fn ($id): bool => $id !== null));
        if ($eventosCriados) {
            $placeholders = [];
            $parametros = [];
            foreach ($eventosCriados as $indice => $idEvento) {
                $chave = 'evento_' . $indice;
                $placeholders[] = ':' . $chave;
                $parametros[$chave] = $idEvento;
            }
            $consulta = $pdo->prepare(
                'DELETE FROM patrocinios WHERE evento_id IN (' . implode(', ', $placeholders) . ')'
            );
            $consulta->execute($parametros);
        }
    }
    if ($usuarioId !== null) {
        $consulta = $pdo->prepare('DELETE FROM parceiros WHERE criado_por IN (:usuario_id, :outro_usuario_id)');
        $consulta->execute([
            'usuario_id' => $usuarioId,
            'outro_usuario_id' => $outroUsuarioId ?? $usuarioId,
        ]);
        if ($outroUsuarioId !== null) {
            $consulta = $pdo->prepare('DELETE FROM evento_organizadores WHERE usuario_id = :usuario_id');
            $consulta->execute(['usuario_id' => $outroUsuarioId]);
        }
    }
    foreach ([$eventoA, $eventoB, $eventoAlheio] as $idEvento) {
        if ($idEvento !== null) {
            $consulta = $pdo->prepare('DELETE FROM eventos WHERE id = :evento_id');
            $consulta->execute(['evento_id' => $idEvento]);
        }
    }
    foreach ([$usuarioId, $outroUsuarioId] as $idUsuario) {
        if ($idUsuario !== null) {
            $consulta = $pdo->prepare('DELETE FROM usuarios WHERE id = :usuario_id');
            $consulta->execute(['usuario_id' => $idUsuario]);
        }
    }
}
