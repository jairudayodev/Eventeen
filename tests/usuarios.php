<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/usuarios.php';

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

// Os testes de "último administrador" exigem que nenhum outro administrador exista no banco.
$administradoresExistentes = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'administrador'")->fetchColumn();

try {
    $nonce = bin2hex(random_bytes(6));
    $inserir = $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, :senha_hash, :perfil)'
    );
    foreach ([
        'admin' => 'administrador',
        'organizador' => 'organizador',
        'coorganizador' => 'organizador',
        'participante' => 'participante',
    ] as $chave => $perfil) {
        $inserir->execute([
            'nome' => 'Usuário ' . $chave,
            'email' => "usuarios-{$chave}-{$nonce}@example.invalid",
            'senha_hash' => password_hash('senha-de-teste', PASSWORD_DEFAULT),
            'perfil' => $perfil,
        ]);
        $usuarios[$chave] = (int) $pdo->lastInsertId();
    }

    // RNF02: a mesma senha gera hashes diferentes (salt) e continua verificável.
    $hashA = password_hash('mesma-senha', PASSWORD_DEFAULT);
    $hashB = password_hash('mesma-senha', PASSWORD_DEFAULT);
    $assert($hashA !== $hashB && password_verify('mesma-senha', $hashA) && password_verify('mesma-senha', $hashB), 'O hash de senha não usa salt.');

    // Concessão de perfil de organizador a um participante.
    atualizarUsuarioAdministracao($pdo, $usuarios['participante'], 'Participante Promovido', "usuarios-participante-{$nonce}@example.invalid", 'RA123', 'organizador');
    $assert(buscarUsuario($pdo, $usuarios['participante'])['perfil'] === 'organizador', 'O perfil de organizador não foi concedido.');
    atualizarUsuarioAdministracao($pdo, $usuarios['participante'], 'Participante Promovido', "usuarios-participante-{$nonce}@example.invalid", 'RA123', 'participante');
    $assert(buscarUsuario($pdo, $usuarios['participante'])['perfil'] === 'participante', 'O perfil de organizador não foi revogado.');

    $esperarRejeicao(
        static fn () => atualizarUsuarioAdministracao($pdo, $usuarios['participante'], 'Nome', "usuarios-admin-{$nonce}@example.invalid", '', 'participante'),
        'já está em uso'
    );
    $esperarRejeicao(
        static fn () => atualizarUsuarioAdministracao($pdo, $usuarios['participante'], 'Nome', 'email-invalido', '', 'participante'),
        'e-mail válido'
    );
    $esperarRejeicao(
        static fn () => atualizarUsuarioAdministracao($pdo, $usuarios['participante'], 'Nome', "usuarios-participante-{$nonce}@example.invalid", '', 'root'),
        'perfil de acesso válido'
    );

    if ($administradoresExistentes === 0) {
        $esperarRejeicao(
            static fn () => atualizarUsuarioAdministracao($pdo, $usuarios['admin'], 'Usuário admin', "usuarios-admin-{$nonce}@example.invalid", '', 'participante'),
            'último administrador'
        );
    }
    // Com um segundo administrador, a revogação do primeiro é permitida.
    atualizarUsuarioAdministracao($pdo, $usuarios['coorganizador'], 'Usuário coorganizador', "usuarios-coorganizador-{$nonce}@example.invalid", '', 'administrador');
    atualizarUsuarioAdministracao($pdo, $usuarios['admin'], 'Usuário admin', "usuarios-admin-{$nonce}@example.invalid", '', 'participante');
    $assert(buscarUsuario($pdo, $usuarios['admin'])['perfil'] === 'participante', 'A revogação com outro administrador ativo falhou.');

    // Organizador único de um evento ativo não perde o perfil.
    $pdo->prepare(
        "INSERT INTO eventos (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
         VALUES (:titulo, 'Evento de teste', 'Teste', 'Sala', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 6 DAY), 10, 'publicado')"
    )->execute(['titulo' => 'Evento único ' . $nonce]);
    $eventoId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO evento_organizadores (evento_id, usuario_id) VALUES (:evento_id, :usuario_id)')
        ->execute(['evento_id' => $eventoId, 'usuario_id' => $usuarios['organizador']]);
    $esperarRejeicao(
        static fn () => atualizarUsuarioAdministracao($pdo, $usuarios['organizador'], 'Usuário organizador', "usuarios-organizador-{$nonce}@example.invalid", '', 'participante'),
        'único responsável'
    );

    $totais = contarUsuariosPorPerfil($pdo);
    $assert($totais['total'] >= 4, 'A contagem de usuários por perfil está incorreta.');
    $lista = listarUsuarios($pdo, "usuarios-organizador-{$nonce}", '');
    $assert(count($lista) === 1 && (int) $lista[0]['eventos_organizados'] === 1, 'A busca de usuários não encontrou o organizador.');

    echo "OK: {$assertions} verificações de usuários, perfis e permissões.\n";
} finally {
    if ($eventoId !== null) {
        $pdo->prepare('DELETE FROM eventos WHERE id = :id')->execute(['id' => $eventoId]);
    }
    foreach ($usuarios as $usuarioId) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $usuarioId]);
    }
}
