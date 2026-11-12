<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/autenticacao.php';
require __DIR__ . '/../includes/email.php';

$consultaTabelas = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name IN ('tentativas_acesso', 'redefinicoes_senha')"
);
if ((int) $consultaTabelas->fetchColumn() !== 2) {
    fwrite(STDERR, "Atualize o banco com banco.sql antes de executar os testes de autenticação.\n");
    exit(2);
}

$usuarioId = null;
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

$nonce = bin2hex(random_bytes(6));
$email = "autenticacao-{$nonce}@example.invalid";
$ip = '203.0.113.' . random_int(1, 254);
$ipOutro = '198.51.100.' . random_int(1, 254);

try {
    $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES ('Pessoa de teste', :email, :hash, 'participante')"
    )->execute(['email' => $email, 'hash' => password_hash('senha-correta', PASSWORD_DEFAULT)]);
    $usuarioId = (int) $pdo->lastInsertId();

    // Login correto e incorreto.
    $usuario = autenticarUsuario($pdo, $email, 'senha-correta', $ip);
    $assert((int) $usuario['id'] === $usuarioId, 'O login correto deveria devolver o usuário.');
    $esperarRejeicao(fn () => autenticarUsuario($pdo, $email, 'errada', $ip), 'E-mail ou senha incorretos');
    $esperarRejeicao(fn () => autenticarUsuario($pdo, "inexistente-{$nonce}@example.invalid", 'x', $ip), 'E-mail ou senha incorretos');

    // Um acerto zera as falhas daquele e-mail.
    autenticarUsuario($pdo, $email, 'senha-correta', $ip);
    $consulta = $pdo->prepare("SELECT COUNT(*) FROM tentativas_acesso WHERE tipo = 'login' AND email = :email");
    $consulta->execute(['email' => $email]);
    $assert((int) $consulta->fetchColumn() === 0, 'O login bem-sucedido deveria limpar as falhas do e-mail.');

    // Cinco falhas bloqueiam o e-mail, mesmo com a senha certa e vindo de outro IP.
    for ($i = 0; $i < LOGIN_FALHAS_POR_EMAIL; $i++) {
        $esperarRejeicao(fn () => autenticarUsuario($pdo, $email, 'errada', $ip), 'E-mail ou senha incorretos');
    }
    $esperarRejeicao(fn () => autenticarUsuario($pdo, $email, 'senha-correta', $ipOutro), 'Muitas tentativas');
    $esperarRejeicao(fn () => autenticarUsuario($pdo, strtoupper($email), 'senha-correta', $ipOutro), 'Muitas tentativas');

    // Falhas antigas (fora da janela de 15 minutos) deixam de contar.
    $pdo->prepare("UPDATE tentativas_acesso SET criado_em = :antigo WHERE tipo = 'login' AND email = :email")
        ->execute(['antigo' => date('Y-m-d H:i:s', time() - 16 * 60), 'email' => $email]);
    $assert((int) autenticarUsuario($pdo, $email, 'senha-correta', $ip)['id'] === $usuarioId, 'O bloqueio deveria expirar após a janela.');

    // Limite por IP: muitas contas diferentes a partir do mesmo endereço.
    $ipAtaque = '192.0.2.' . random_int(1, 254);
    $pdo->prepare('DELETE FROM tentativas_acesso WHERE ip = :ip')->execute(['ip' => $ipAtaque]);
    for ($i = 0; $i < LOGIN_FALHAS_POR_IP; $i++) {
        $esperarRejeicao(fn () => autenticarUsuario($pdo, "alvo{$i}-{$nonce}@example.invalid", 'x', $ipAtaque), 'E-mail ou senha incorretos');
    }
    $esperarRejeicao(fn () => autenticarUsuario($pdo, $email, 'senha-correta', $ipAtaque), 'Muitas tentativas');
    $pdo->prepare('DELETE FROM tentativas_acesso WHERE ip = :ip')->execute(['ip' => $ipAtaque]);

    // Recuperação de senha: e-mail sem conta não gera link, mas também não dá erro.
    $assert(solicitarRedefinicaoSenha($pdo, "ninguem-{$nonce}@example.invalid", $ip) === null, 'E-mail sem conta não deveria gerar link.');
    $primeiro = solicitarRedefinicaoSenha($pdo, $email, $ip);
    $assert($primeiro !== null && strlen($primeiro['token']) === 64, 'Deveria gerar um código de 64 caracteres.');
    $consulta = $pdo->prepare('SELECT token_hash FROM redefinicoes_senha WHERE usuario_id = :id');
    $consulta->execute(['id' => $usuarioId]);
    $assert($consulta->fetchColumn() === hash('sha256', $primeiro['token']), 'O banco deveria guardar só o hash do código.');

    // Um novo pedido invalida o link anterior.
    $segundo = solicitarRedefinicaoSenha($pdo, $email, $ip);
    $assert(usuarioDoTokenRedefinicao($pdo, $primeiro['token']) === null, 'O link anterior deveria deixar de valer.');
    $assert((int) usuarioDoTokenRedefinicao($pdo, $segundo['token'])['id'] === $usuarioId, 'O link mais recente deveria valer.');
    $assert(usuarioDoTokenRedefinicao($pdo, 'abc') === null, 'Código malformado deveria ser recusado.');

    // Terceiro pedido no mesmo e-mail ainda passa; o quarto é bloqueado.
    solicitarRedefinicaoSenha($pdo, $email, $ipOutro);
    $esperarRejeicao(fn () => solicitarRedefinicaoSenha($pdo, $email, $ipOutro), 'vários pedidos de recuperação');
    $consulta = $pdo->prepare('SELECT token_hash FROM redefinicoes_senha WHERE usuario_id = :id AND usado_em IS NULL');
    $consulta->execute(['id' => $usuarioId]);
    $assert(count($consulta->fetchAll()) === 1, 'Só um link deveria ficar pendente por usuário.');

    // Gera um link válido conhecido para os testes de troca.
    $pdo->prepare("DELETE FROM tentativas_acesso WHERE tipo = 'recuperacao' AND email = :email")->execute(['email' => $email]);
    $pedido = solicitarRedefinicaoSenha($pdo, $email, $ip);

    $esperarRejeicao(fn () => redefinirSenhaComToken($pdo, $pedido['token'], 'curta', 'curta'), 'pelo menos 8');
    $esperarRejeicao(fn () => redefinirSenhaComToken($pdo, $pedido['token'], 'nova-senha-1', 'outra-senha'), 'confirmação');

    // A troca libera o login bloqueado e o link vale uma única vez.
    for ($i = 0; $i < LOGIN_FALHAS_POR_EMAIL; $i++) {
        registrarTentativaAcesso($pdo, 'login', $email, $ip);
    }
    redefinirSenhaComToken($pdo, $pedido['token'], 'nova-senha-1', 'nova-senha-1');
    $esperarRejeicao(fn () => redefinirSenhaComToken($pdo, $pedido['token'], 'nova-senha-2', 'nova-senha-2'), 'inválido, já foi usado ou expirou');
    $esperarRejeicao(fn () => autenticarUsuario($pdo, $email, 'senha-correta', $ip), 'E-mail ou senha incorretos');
    $assert((int) autenticarUsuario($pdo, $email, 'nova-senha-1', $ip)['id'] === $usuarioId, 'A nova senha deveria funcionar.');

    // Link expirado.
    $pdo->prepare("DELETE FROM tentativas_acesso WHERE tipo = 'recuperacao' AND email = :email")->execute(['email' => $email]);
    $expirado = solicitarRedefinicaoSenha($pdo, $email, $ip);
    $pdo->prepare('UPDATE redefinicoes_senha SET expira_em = :passado WHERE usuario_id = :id AND usado_em IS NULL')
        ->execute(['passado' => date('Y-m-d H:i:s', time() - 60), 'id' => $usuarioId]);
    $assert(usuarioDoTokenRedefinicao($pdo, $expirado['token']) === null, 'Link expirado não deveria valer.');
    $esperarRejeicao(fn () => redefinirSenhaComToken($pdo, $expirado['token'], 'nova-senha-3', 'nova-senha-3'), 'expirou');

    // Sessão: limites de inatividade e assinatura da senha.
    $assert(limiteInatividadeSessao('participante', false) === 7200, 'Sem "manter conectado", a sessão deveria durar 2 horas sem uso.');
    $assert(limiteInatividadeSessao('participante', true) === 30 * 86400, 'Participante com "manter conectado": 30 dias.');
    $assert(limiteInatividadeSessao('organizador', true) === 12 * 3600, 'Organizador com "manter conectado": no máximo 12 horas.');
    $assert(limiteInatividadeSessao('administrador', true) === 12 * 3600, 'Administrador com "manter conectado": no máximo 12 horas.');
    $assert(assinaturaSenha('a') !== assinaturaSenha('b'), 'Senhas diferentes deveriam ter assinaturas diferentes.');

    // E-mail: cabeçalhos codificados e sem quebra de linha injetada.
    $assert(codificarCabecalhoEmail("Inscrição\r\nBcc: x@y") === '=?UTF-8?B?' . base64_encode('Inscrição  Bcc: x@y') . '?=', 'Quebras de linha deveriam ser removidas do cabeçalho.');
    $assert(enviarEmail("x@example.invalid\r\nBcc: y@example.invalid", 'Teste', 'Corpo') === false, 'Destinatário inválido deveria ser recusado.');

    echo "OK - autenticacao: {$assertions} verificações\n";
} catch (Throwable $erro) {
    fwrite(STDERR, 'FALHOU - autenticacao: ' . $erro->getMessage() . "\n");
    exit(1);
} finally {
    if ($usuarioId !== null) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $usuarioId]);
    }
    $pdo->prepare('DELETE FROM tentativas_acesso WHERE email LIKE :padrao')->execute(['padrao' => "%-{$nonce}@example.invalid"]);
}
