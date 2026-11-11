<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

const PERFIS_USUARIO = [
    'participante' => 'Participante',
    'organizador' => 'Organizador',
    'administrador' => 'Administrador',
];

function rotuloPerfil(string $perfil): string
{
    return PERFIS_USUARIO[$perfil] ?? ucfirst($perfil);
}

function contarUsuariosPorPerfil(PDO $pdo): array
{
    $totais = array_fill_keys(array_keys(PERFIS_USUARIO), 0);
    foreach ($pdo->query('SELECT perfil, COUNT(*) AS total FROM usuarios GROUP BY perfil') as $linha) {
        $totais[$linha['perfil']] = (int) $linha['total'];
    }
    $totais['total'] = array_sum($totais);

    return $totais;
}

function listarUsuarios(PDO $pdo, string $termo = '', string $perfil = '', int $limite = 200): array
{
    $sql = 'SELECT u.id, u.nome, u.email, u.matricula, u.perfil, u.criado_em,
                   (SELECT COUNT(*) FROM evento_organizadores eo WHERE eo.usuario_id = u.id) AS eventos_organizados,
                   (SELECT COUNT(*) FROM inscricoes i WHERE i.usuario_id = u.id AND i.status = \'ativa\') AS inscricoes_ativas
            FROM usuarios u
            WHERE 1 = 1';
    $parametros = [];
    $termo = trim($termo);
    if ($termo !== '') {
        $sql .= ' AND (u.nome LIKE :termo_nome OR u.email LIKE :termo_email OR u.matricula LIKE :termo_matricula)';
        $parametros['termo_nome'] = '%' . $termo . '%';
        $parametros['termo_email'] = '%' . $termo . '%';
        $parametros['termo_matricula'] = '%' . $termo . '%';
    }
    if ($perfil !== '' && isset(PERFIS_USUARIO[$perfil])) {
        $sql .= ' AND u.perfil = :perfil';
        $parametros['perfil'] = $perfil;
    }
    $sql .= ' ORDER BY u.criado_em DESC, u.id DESC LIMIT ' . max(1, min($limite, 1000));
    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

function buscarUsuario(PDO $pdo, int $usuarioId): ?array
{
    $consulta = $pdo->prepare(
        'SELECT id, nome, email, matricula, perfil, criado_em, atualizado_em
         FROM usuarios WHERE id = :id'
    );
    $consulta->execute(['id' => $usuarioId]);
    $usuario = $consulta->fetch();

    return $usuario ?: null;
}

/**
 * Atualiza dados e perfil de um usuário (RF03).
 * Regras: e-mail e matrícula únicos; o último administrador não pode ser rebaixado;
 * um organizador que seja o único responsável por algum evento não perde o perfil.
 */
function atualizarUsuarioAdministracao(
    PDO $pdo,
    int $usuarioId,
    string $nome,
    string $email,
    string $matricula,
    string $perfil
): void {
    $nome = trim($nome);
    $email = trim($email);
    $matricula = trim($matricula);
    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 150) {
        throw new DomainException('Informe o nome do usuário, com até 150 caracteres.');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email, 'UTF-8') > 190) {
        throw new DomainException('Informe um e-mail válido.');
    }
    if (mb_strlen($matricula, 'UTF-8') > 40) {
        throw new DomainException('A matrícula deve ter no máximo 40 caracteres.');
    }
    if (!isset(PERFIS_USUARIO[$perfil])) {
        throw new DomainException('Selecione um perfil de acesso válido.');
    }

    $pdo->beginTransaction();
    try {
        // Bloqueia os administradores para serializar revogações simultâneas.
        $consulta = $pdo->query("SELECT id FROM usuarios WHERE perfil = 'administrador' FOR UPDATE");
        $administradores = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));

        $consulta = $pdo->prepare('SELECT id, perfil FROM usuarios WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $usuarioId]);
        $atual = $consulta->fetch();
        if (!$atual) {
            throw new DomainException('Usuário não encontrado.');
        }

        if ($atual['perfil'] === 'administrador' && $perfil !== 'administrador' && count($administradores) <= 1) {
            throw new DomainException('Não é possível revogar o último administrador do sistema.');
        }

        if ($atual['perfil'] === 'organizador' && $perfil !== 'organizador') {
            $consulta = $pdo->prepare(
                "SELECT e.titulo
                 FROM evento_organizadores eo
                 JOIN eventos e ON e.id = eo.evento_id
                 WHERE eo.usuario_id = :usuario_id
                   AND e.status IN ('rascunho', 'publicado')
                   AND (SELECT COUNT(*) FROM evento_organizadores outro WHERE outro.evento_id = eo.evento_id) = 1
                 LIMIT 1"
            );
            $consulta->execute(['usuario_id' => $usuarioId]);
            $eventoUnico = $consulta->fetchColumn();
            if ($eventoUnico !== false) {
                throw new DomainException(
                    'Este organizador é o único responsável pelo evento "' . $eventoUnico
                    . '". Inclua outro organizador no evento antes de revogar o acesso.'
                );
            }
        }

        try {
            $consulta = $pdo->prepare(
                'UPDATE usuarios
                 SET nome = :nome, email = :email, matricula = :matricula, perfil = :perfil
                 WHERE id = :id'
            );
            $consulta->execute([
                'nome' => $nome,
                'email' => $email,
                'matricula' => $matricula !== '' ? $matricula : null,
                'perfil' => $perfil,
                'id' => $usuarioId,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new DomainException('Este e-mail ou matrícula já está em uso por outra conta.');
            }
            throw $exception;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
