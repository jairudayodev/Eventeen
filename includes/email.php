<?php
declare(strict_types=1);

/*
 * Envio de e-mails transacionais (recuperação de senha e confirmação de inscrição).
 *
 * Transportes, definidos em config.local.php ('email' => ['transporte' => ...]):
 * - 'arquivo'    (padrão): grava cada mensagem em storage/emails como .eml (desenvolvimento);
 * - 'smtp'       : envia por um servidor SMTP (porta 587 com TLS ou 465 com SSL);
 * - 'mail'       : usa a função mail() do PHP (servidor com sendmail configurado);
 * - 'desativado' : não envia nada.
 *
 * Uma falha de envio é registrada no log e não interrompe a operação principal.
 */

function enviarEmail(string $para, string $assunto, string $texto): bool
{
    $config = appConfig('email');
    $para = str_replace(["\r", "\n"], '', trim($para));
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        $remetente = str_replace(["\r", "\n"], '', (string) $config['remetente']);
        $cabecalhos = [
            'Date' => date(DATE_RFC2822),
            'From' => sprintf('%s <%s>', codificarCabecalhoEmail((string) $config['nome_remetente']), $remetente),
            'To' => $para,
            'Subject' => codificarCabecalhoEmail($assunto),
            'Message-ID' => sprintf('<%s@%s>', bin2hex(random_bytes(12)), substr(strrchr($remetente, '@') ?: '@eventeen.local', 1)),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
        ];
        $corpo = rtrim(chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $texto))), 76, "\r\n"));

        switch ($config['transporte']) {
            case 'desativado':
                return true;
            case 'arquivo':
                $arquivo = appPastaDados('emails') . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
                return file_put_contents($arquivo, montarCabecalhosEmail($cabecalhos) . "\r\n\r\n" . $corpo . "\r\n") !== false;
            case 'mail':
                $extras = $cabecalhos;
                unset($extras['To'], $extras['Subject']);
                return mail($para, $cabecalhos['Subject'], $corpo, montarCabecalhosEmail($extras));
            case 'smtp':
                enviarEmailSmtp((array) $config['smtp'], $remetente, $para, montarCabecalhosEmail($cabecalhos) . "\r\n\r\n" . $corpo);
                return true;
            default:
                throw new RuntimeException('Transporte de e-mail desconhecido: ' . $config['transporte']);
        }
    } catch (Throwable $erro) {
        error_log('Falha ao enviar e-mail para ' . $para . ': ' . $erro->getMessage());
        return false;
    }
}

function codificarCabecalhoEmail(string $texto): string
{
    $texto = str_replace(["\r", "\n"], ' ', $texto);
    return preg_match('/[^\x20-\x7E]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
}

function montarCabecalhosEmail(array $cabecalhos): string
{
    $linhas = [];
    foreach ($cabecalhos as $nome => $valor) {
        $linhas[] = $nome . ': ' . $valor;
    }

    return implode("\r\n", $linhas);
}

/** Cliente SMTP mínimo (EHLO, STARTTLS, AUTH LOGIN, MAIL, RCPT, DATA). */
function enviarEmailSmtp(array $smtp, string $de, string $para, string $mensagem): void
{
    $seguranca = (string) ($smtp['seguranca'] ?? 'tls');
    $tempoLimite = (int) ($smtp['tempo_limite'] ?? 15);
    $endereco = ($seguranca === 'ssl' ? 'ssl://' : 'tcp://') . $smtp['host'] . ':' . (int) $smtp['porta'];
    $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $conexao = @stream_socket_client($endereco, $codigoErro, $mensagemErro, $tempoLimite, STREAM_CLIENT_CONNECT, $contexto);
    if (!$conexao) {
        throw new RuntimeException("SMTP: não foi possível conectar a {$endereco} ({$mensagemErro}).");
    }
    stream_set_timeout($conexao, $tempoLimite);

    $ler = static function () use ($conexao): array {
        $texto = '';
        while (($linha = fgets($conexao, 1024)) !== false) {
            $texto .= $linha;
            if (strlen($linha) < 4 || $linha[3] === ' ') {
                break;
            }
        }
        if ($texto === '') {
            throw new RuntimeException('SMTP: o servidor não respondeu.');
        }

        return [(int) substr($texto, 0, 3), trim($texto)];
    };
    $comando = static function (?string $linha, array $esperados, string $rotulo = '') use ($conexao, $ler): void {
        if ($linha !== null) {
            fwrite($conexao, $linha . "\r\n");
        }
        [$codigo, $resposta] = $ler();
        if (!in_array($codigo, $esperados, true)) {
            throw new RuntimeException('SMTP: resposta inesperada a ' . ($rotulo ?: strtok((string) $linha, ' ')) . ': ' . $resposta);
        }
    };

    try {
        $comando(null, [220], 'conexão');
        $hostLocal = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) (gethostname() ?: 'localhost')) ?: 'localhost';
        $comando('EHLO ' . $hostLocal, [250]);
        if ($seguranca === 'tls') {
            $comando('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP: falha ao iniciar TLS.');
            }
            $comando('EHLO ' . $hostLocal, [250]);
        }
        if ((string) ($smtp['usuario'] ?? '') !== '') {
            $comando('AUTH LOGIN', [334]);
            $comando(base64_encode((string) $smtp['usuario']), [334], 'usuário');
            $comando(base64_encode((string) $smtp['senha']), [235], 'senha');
        }
        $comando('MAIL FROM:<' . $de . '>', [250]);
        $comando('RCPT TO:<' . $para . '>', [250, 251]);
        $comando('DATA', [354]);
        $comando(preg_replace('/^\./m', '..', $mensagem) . "\r\n.", [250], 'mensagem');
        fwrite($conexao, "QUIT\r\n");
    } finally {
        fclose($conexao);
    }
}

/** Confirmação enviada ao participante logo após a inscrição (falha não impede a inscrição). */
function notificarInscricaoConfirmada(PDO $pdo, array $usuario, int $eventoId): void
{
    $consulta = $pdo->prepare('SELECT titulo, local, data_inicio, data_fim, valor_inscricao FROM eventos WHERE id = :id');
    $consulta->execute(['id' => $eventoId]);
    $evento = $consulta->fetch();
    if (!$evento) {
        return;
    }

    $linhas = [
        'Olá, ' . $usuario['nome'] . '.',
        '',
        'Sua inscrição no evento "' . $evento['titulo'] . '" está confirmada.',
        '',
        'Quando: ' . formatPeriodo((string) $evento['data_inicio'], (string) $evento['data_fim']),
        'Onde: ' . $evento['local'],
    ];
    if ((float) $evento['valor_inscricao'] > 0) {
        $linhas[] = 'Valor da inscrição: R$ ' . number_format((float) $evento['valor_inscricao'], 2, ',', '.')
            . ' (o pagamento é registrado pela organização do evento).';
    }
    array_push(
        $linhas,
        '',
        'Você pode consultar a programação ou cancelar a inscrição até 24 horas antes do início em:',
        applicationBaseUrl() . '/minhas-inscricoes.php',
        '',
        'Equipe Eventeen'
    );

    enviarEmail((string) $usuario['email'], 'Inscrição confirmada: ' . $evento['titulo'], implode("\n", $linhas));
}
