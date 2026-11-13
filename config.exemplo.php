<?php
/*
 * Modelo de configuração do servidor.
 *
 * 1. Copie este arquivo para config.local.php (na mesma pasta).
 * 2. Preencha os valores do seu ambiente.
 * O config.local.php fica fora do Git (.gitignore): nunca envie senhas ao repositório.
 * Itens omitidos usam os valores padrão de config.php (ambiente local do XAMPP).
 */
return [
    // 'producao' oculta mensagens técnicas e grava erros em storage/logs/php-erros.log.
    'ambiente' => 'producao',

    // Endereço público, usado nos links enviados por e-mail e nos certificados.
    'url_base' => 'https://eventeen.exemplo.edu.br',

    // Redireciona todo acesso HTTP para HTTPS (exige certificado instalado no servidor).
    'forcar_https' => true,

    // Código que libera o cadastro público de organizadores. Deixe '' para desativar esse cadastro
    // (aí só o administrador concede o perfil de organizador).
    'codigo_organizador' => 'troque-este-codigo',

    // Fuso horário dos eventos (o banco é ajustado para o mesmo fuso).
    'fuso_horario' => 'America/Sao_Paulo',

    // Pasta de logs e e-mails gerados. De preferência, fora da pasta publicada na web.
    // 'pasta_dados' => '/var/eventeen-dados',

    'db' => [
        'host' => '127.0.0.1',
        'nome' => 'eventeen',
        // Use um usuário próprio do banco, sem privilégios administrativos.
        'usuario' => 'eventeen_app',
        'senha' => 'troque-esta-senha',
    ],

    'email' => [
        // 'smtp', 'mail' (sendmail do servidor), 'arquivo' (grava em storage/emails) ou 'desativado'.
        'transporte' => 'smtp',
        'remetente' => 'nao-responda@exemplo.edu.br',
        'nome_remetente' => 'Eventeen',
        'smtp' => [
            'host' => 'smtp.exemplo.edu.br',
            'porta' => 587,
            // 'tls' (porta 587, STARTTLS), 'ssl' (porta 465) ou 'nenhuma'.
            'seguranca' => 'tls',
            'usuario' => 'nao-responda@exemplo.edu.br',
            'senha' => 'senha-do-email',
        ],
    ],
];
