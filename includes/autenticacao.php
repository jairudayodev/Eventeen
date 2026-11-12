<?php
declare(strict_types=1);

/*
 * Autenticação: login com limite de tentativas e recuperação de senha por e-mail.
 *
 * Limites (janela deslizante):
 * - login: 5 falhas por e-mail ou 20 por endereço IP em 15 minutos;
 * - recuperação de senha: 3 pedidos por e-mail ou 10 por IP em 60 minutos.
 */

const LOGIN_FALHAS_POR_EMAIL = 5;
const LOGIN_FALHAS_POR_IP = 20;
const LOGIN_JANELA_MINUTOS = 15;
const RECUPERACAO_PEDIDOS_POR_EMAIL = 3;
const RECUPERACAO_PEDIDOS_POR_IP = 10;
const RECUPERACAO_JANELA_MINUTOS = 60;
const REDEFINICAO_VALIDADE_MINUTOS = 60;
const SENHA_TAMANHO_MINIMO = 8;

function ipCliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

function normalizarEmailAcesso(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

/** Minutos que faltam para liberar novas tentativas (0 = liberado). */
function minutosBloqueioAcesso(PDO $pdo, string $tipo, string $email, string $ip): int
{
    [$porEmail, $porIp, $janela] = $tipo === 'login'
        ? [LOGIN_FALHAS_POR_EMAIL, LOGIN_FALHAS_POR_IP, LOGIN_JANELA_MINUTOS]
        : [RECUPERACAO_PEDIDOS_POR_EMAIL, RECUPERACAO_PEDIDOS_POR_IP, RECUPERACAO_JANELA_MINUTOS];
    $agora = time();
    $desde = date('Y-m-d H:i:s', $agora - $janela * 60);
    $maiorEspera = 0;

    foreach ([['email', normalizarEmailAcesso($email), $porEmail], ['ip', $ip, $porIp]] as [$coluna, $valor, $limite]) {
        $consulta = $pdo->prepare(
            "SELECT COUNT(*) AS total, MIN(criado_em) AS primeira
             FROM tentativas_acesso
             WHERE tipo = :tipo AND {$coluna} = :valor AND criado_em >= :desde"
        );
        $consulta->execute(['tipo' => $tipo, 'valor' => $valor, 'desde' => $desde]);
        $linha = $consulta->fetch();
        if ((int) $linha['total'] >= $limite) {
            $liberacao = strtotime((string) $linha['primeira']) + $janela * 60;
            $maiorEspera = max($maiorEspera, (int) ceil(($liberacao - $agora) / 60), 1);
        }
    }

    return $maiorEspera;
}

function registrarTentativaAcesso(PDO $pdo, string $tipo, string $email, string $ip): void
{
    $pdo->prepare(
        'INSERT INTO tentativas_acesso (tipo, email, ip, criado_em) VALUES (:tipo, :email, :ip, :criado_em)'
    )->execute([
        'tipo' => $tipo,
        'email' => substr(normalizarEmailAcesso($email), 0, 190),
        'ip' => $ip,
        'criado_em' => date('Y-m-d H:i:s'),
    ]);
    // Registros antigos não influenciam mais nenhum limite.
    $pdo->prepare('DELETE FROM tentativas_acesso WHERE criado_em < :limite')
        ->execute(['limite' => date('Y-m-d H:i:s', time() - 24 * 60 * 60)]);
}

function limparTentativasAcesso(PDO $pdo, string $tipo, string $email): void
{
    $pdo->prepare('DELETE FROM tentativas_acesso WHERE tipo = :tipo AND email = :email')
        ->execute(['tipo' => $tipo, 'email' => normalizarEmailAcesso($email)]);
}

/**
 * Confere e-mail e senha. Devolve o usuário ou lança DomainException com a
 * mensagem a exibir (credenciais incorretas ou excesso de tentativas).
 */
function autenticarUsuario(PDO $pdo, string $email, string $senha, string $ip): array
{
    $espera = minutosBloqueioAcesso($pdo, 'login', $email, $ip);
    if ($espera > 0) {
        throw new DomainException(sprintf(
            'Muitas tentativas de acesso. Aguarde %d %s e tente novamente, ou redefina sua senha.',
            $espera,
            $espera === 1 ? 'minuto' : 'minutos'
        ));
    }

    $consulta = $pdo->prepare('SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email = :email LIMIT 1');
    $consulta->execute(['email' => $email]);
    $usuario = $consulta->fetch();

    // Sem conta, a senha é conferida contra um hash fixo para que o tempo de
    // resposta não revele quais e-mails estão cadastrados.
    $hash = $usuario ? (string) $usuario['senha_hash'] : '$2y$10$hVv6oddOFG1ClRdLx8CmdO0ws0UXswiJ.bzrZSh1in4.nUdMkp2Z6';
    if (!password_verify($senha, $hash) || !$usuario) {
        registrarTentativaAcesso($pdo, 'login', $email, $ip);
        throw new DomainException('E-mail ou senha incorretos.');
    }

    limparTentativasAcesso($pdo, 'login', $email);
    if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
        $usuario['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE usuarios SET senha_hash = :hash WHERE id = :id')
            ->execute(['hash' => $usuario['senha_hash'], 'id' => $usuario['id']]);
    }

    return $usuario;
}

/**
 * Registra um pedido de recuperação de senha. Devolve o usuário e o código a
 * enviar por e-mail, ou null quando não há conta com esse e-mail (a página
 * mostra a mesma mensagem nos dois casos).
 */
function solicitarRedefinicaoSenha(PDO $pdo, string $email, string $ip): ?array
{
    $espera = minutosBloqueioAcesso($pdo, 'recuperacao', $email, $ip);
    if ($espera > 0) {
        throw new DomainException(sprintf(
            'Já recebemos vários pedidos de recuperação há pouco. Confira sua caixa de entrada e o spam, ou aguarde %d %s para pedir de novo.',
            $espera,
            $espera === 1 ? 'minuto' : 'minutos'
        ));
    }
    registrarTentativaAcesso($pdo, 'recuperacao', $email, $ip);

    $consulta = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE email = :email LIMIT 1');
    $consulta->execute(['email' => $email]);
    $usuario = $consulta->fetch();
    if (!$usuario) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $agora = time();
    $pdo->beginTransaction();
    try {
        // Só o link mais recente vale.
        $pdo->prepare('DELETE FROM redefinicoes_senha WHERE usuario_id = :usuario_id AND usado_em IS NULL')
            ->execute(['usuario_id' => $usuario['id']]);
        $pdo->prepare(
            'INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em, criado_em)
             VALUES (:usuario_id, :token_hash, :expira_em, :criado_em)'
        )->execute([
            'usuario_id' => $usuario['id'],
            'token_hash' => hash('sha256', $token),
            'expira_em' => date('Y-m-d H:i:s', $agora + REDEFINICAO_VALIDADE_MINUTOS * 60),
            'criado_em' => date('Y-m-d H:i:s', $agora),
        ]);
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    return ['usuario' => $usuario, 'token' => $token];
}

function tokenRedefinicaoBemFormado(string $token): bool
{
    return strlen($token) === 64 && ctype_xdigit($token);
}

/** Usuário dono de um link de redefinição ainda válido, ou null. */
function usuarioDoTokenRedefinicao(PDO $pdo, string $token): ?array
{
    if (!tokenRedefinicaoBemFormado($token)) {
        return null;
    }
    $consulta = $pdo->prepare(
        'SELECT u.id, u.nome, u.email
         FROM redefinicoes_senha r
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.token_hash = :token_hash AND r.usado_em IS NULL AND r.expira_em > :agora
         LIMIT 1'
    );
    $consulta->execute(['token_hash' => hash('sha256', $token), 'agora' => date('Y-m-d H:i:s')]);
    $usuario = $consulta->fetch();

    return $usuario ?: null;
}

/** Troca a senha usando um link de redefinição; o link vale uma única vez. */
function redefinirSenhaComToken(PDO $pdo, string $token, string $novaSenha, string $confirmacao): array
{
    if (strlen($novaSenha) < SENHA_TAMANHO_MINIMO) {
        throw new DomainException('A nova senha deve ter pelo menos ' . SENHA_TAMANHO_MINIMO . ' caracteres.');
    }
    if ($novaSenha !== $confirmacao) {
        throw new DomainException('A confirmação não corresponde à nova senha.');
    }
    $linkInvalido = 'Este link de redefinição é inválido, já foi usado ou expirou. Solicite um novo.';
    if (!tokenRedefinicaoBemFormado($token)) {
        throw new DomainException($linkInvalido);
    }

    $agora = date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT r.id, r.usuario_id, r.expira_em, r.usado_em, u.email
             FROM redefinicoes_senha r
             JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.token_hash = :token_hash
             FOR UPDATE'
        );
        $consulta->execute(['token_hash' => hash('sha256', $token)]);
        $pedido = $consulta->fetch();
        if (!$pedido || $pedido['usado_em'] !== null || $pedido['expira_em'] <= $agora) {
            throw new DomainException($linkInvalido);
        }

        $pdo->prepare('UPDATE usuarios SET senha_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($novaSenha, PASSWORD_DEFAULT), 'id' => $pedido['usuario_id']]);
        $pdo->prepare('UPDATE redefinicoes_senha SET usado_em = :agora WHERE id = :id')
            ->execute(['agora' => $agora, 'id' => $pedido['id']]);
        $pdo->prepare('DELETE FROM redefinicoes_senha WHERE usuario_id = :usuario_id AND usado_em IS NULL')
            ->execute(['usuario_id' => $pedido['usuario_id']]);
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    // Quem prova ter acesso ao e-mail volta a poder entrar imediatamente.
    limparTentativasAcesso($pdo, 'login', (string) $pedido['email']);

    return ['id' => (int) $pedido['usuario_id'], 'email' => $pedido['email']];
}
