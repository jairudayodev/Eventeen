<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

/**
 * Emite (ou reapresenta) o certificado de uma presença confirmada em atividade (RF17, RN09).
 * A operação é idempotente: no máximo um certificado por presença, com código único de validação.
 */
function emitirCertificadoAtividade(PDO $pdo, int $usuarioId, int $atividadeId): string
{
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            "SELECT pa.atividade_id, pa.evento_id, a.inicio, a.fim
             FROM presencas_atividades pa
             JOIN atividades a ON a.id = pa.atividade_id AND a.evento_id = pa.evento_id
             JOIN inscricoes i ON i.usuario_id = pa.usuario_id AND i.evento_id = pa.evento_id
             WHERE pa.usuario_id = :usuario_id AND pa.atividade_id = :atividade_id
               AND i.status = 'ativa'
             FOR UPDATE"
        );
        $consulta->execute(['usuario_id' => $usuarioId, 'atividade_id' => $atividadeId]);
        $presenca = $consulta->fetch();
        if (!$presenca) {
            throw new DomainException('O certificado exige presença confirmada nesta atividade.');
        }
        if (strtotime((string) $presenca['fim']) > time()) {
            throw new DomainException('O certificado fica disponível após o término da atividade.');
        }

        $consulta = $pdo->prepare(
            'SELECT codigo_validacao FROM certificados WHERE atividade_id = :atividade_id AND usuario_id = :usuario_id'
        );
        $consulta->execute(['atividade_id' => $atividadeId, 'usuario_id' => $usuarioId]);
        $codigoExistente = $consulta->fetchColumn();
        if ($codigoExistente !== false) {
            $pdo->commit();
            return (string) $codigoExistente;
        }

        $minutos = max(1, (int) round((strtotime((string) $presenca['fim']) - strtotime((string) $presenca['inicio'])) / 60));
        $cargaHoraria = number_format($minutos / 60, 2, '.', '');
        $inserir = $pdo->prepare(
            'INSERT INTO certificados (atividade_id, usuario_id, evento_id, codigo_validacao, carga_horaria)
             VALUES (:atividade_id, :usuario_id, :evento_id, :codigo, :carga_horaria)'
        );
        for ($tentativa = 0; ; $tentativa++) {
            $codigo = strtoupper(bin2hex(random_bytes(8)));
            try {
                $inserir->execute([
                    'atividade_id' => $atividadeId,
                    'usuario_id' => $usuarioId,
                    'evento_id' => (int) $presenca['evento_id'],
                    'codigo' => $codigo,
                    'carga_horaria' => $cargaHoraria,
                ]);
                break;
            } catch (PDOException $exception) {
                // Colisão de código (improvável): tenta novamente com outro valor.
                if ($exception->getCode() !== '23000' || $tentativa >= 3) {
                    throw $exception;
                }
            }
        }
        $pdo->commit();

        return $codigo;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function normalizarCodigoCertificado(string $codigo): string
{
    return strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $codigo));
}

function formatarCodigoCertificado(string $codigo): string
{
    return implode('-', str_split(normalizarCodigoCertificado($codigo), 4));
}

function buscarCertificadoPorCodigo(PDO $pdo, string $codigo): ?array
{
    $codigo = normalizarCodigoCertificado($codigo);
    if (strlen($codigo) !== 16) {
        return null;
    }
    $consulta = $pdo->prepare(
        'SELECT c.id, c.usuario_id, c.atividade_id, c.codigo_validacao, c.carga_horaria, c.emitido_em,
                u.nome AS participante_nome,
                a.titulo AS atividade_titulo, a.tipo AS atividade_tipo, a.inicio, a.fim,
                e.titulo AS evento_titulo, e.local AS evento_local
         FROM certificados c
         JOIN usuarios u ON u.id = c.usuario_id
         JOIN atividades a ON a.id = c.atividade_id
         JOIN eventos e ON e.id = c.evento_id
         WHERE c.codigo_validacao = :codigo'
    );
    $consulta->execute(['codigo' => $codigo]);
    $certificado = $consulta->fetch();

    return $certificado ?: null;
}

function formatarCargaHoraria(string|float $carga): string
{
    $valor = (float) $carga;
    $texto = rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');

    return $texto . ($valor <= 1.0 ? ' hora' : ' horas');
}

/* ------------------------------------------------------------------
 * Geração de PDF sem dependências externas (fontes padrão Helvetica).
 * ------------------------------------------------------------------ */

function pdfLarguraTexto(string $texto, float $tamanho, bool $negrito = false): float
{
    static $regular = null;
    static $bold = null;
    if ($regular === null) {
        $regular = array_combine(range(32, 126), [
            278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
            1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
            333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
            556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
        ]);
        $bold = array_combine(range(32, 126), [
            278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
            975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
            333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
            611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
        ]);
    }
    $tabela = $negrito ? $bold : $regular;
    // Letras acentuadas usam a largura da letra base.
    $base = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ù' => 'u', 'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
        'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'Ñ' => 'N',
        '–' => '-', '—' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", 'º' => 'o', 'ª' => 'a',
    ]);
    $largura = 0;
    foreach (mb_str_split($base, 1, 'UTF-8') as $caractere) {
        $largura += $tabela[ord($caractere[0])] ?? 556;
    }

    return $largura * $tamanho / 1000;
}

function pdfQuebrarLinhas(string $texto, float $tamanho, float $larguraMaxima, bool $negrito = false): array
{
    $linhas = [];
    $atual = '';
    foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $palavra) {
        $tentativa = $atual === '' ? $palavra : $atual . ' ' . $palavra;
        if ($atual !== '' && pdfLarguraTexto($tentativa, $tamanho, $negrito) > $larguraMaxima) {
            $linhas[] = $atual;
            $atual = $palavra;
        } else {
            $atual = $tentativa;
        }
    }
    if ($atual !== '') {
        $linhas[] = $atual;
    }

    return $linhas;
}

function pdfTexto(string $texto): string
{
    $convertido = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

    return '(' . strtr($convertido, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']) . ')';
}

function pdfLinhaCentralizada(string $texto, float $y, float $tamanho, bool $negrito, float $larguraPagina): string
{
    $x = ($larguraPagina - pdfLarguraTexto($texto, $tamanho, $negrito)) / 2;

    return sprintf("BT /%s %.1F Tf %.2F %.2F Td %s Tj ET\n", $negrito ? 'F2' : 'F1', $tamanho, $x, $y, pdfTexto($texto));
}

/**
 * Gera o PDF (A4 paisagem) do certificado a partir dos dados de buscarCertificadoPorCodigo().
 */
function gerarPdfCertificado(array $certificado, string $urlValidacao): string
{
    $largura = 842.0;
    $altura = 595.0;
    $inicio = strtotime((string) $certificado['inicio']);
    $fim = strtotime((string) $certificado['fim']);
    $codigo = formatarCodigoCertificado((string) $certificado['codigo_validacao']);

    $conteudo = "q 0.753 0.090 0.176 rg 0 0 842 595 re f Q\n";
    $conteudo .= "q 1 1 1 rg 18 18 806 559 re f Q\n";
    $conteudo .= "q 0.753 0.090 0.176 RG 2 w 32 32 778 531 re S Q\n";
    $conteudo .= "q 0.753 0.090 0.176 rg 32 515 778 48 re f Q\n";
    $conteudo .= "1 1 1 rg\n";
    $conteudo .= pdfLinhaCentralizada('EVENTEEN · FATEC OURINHOS', 532, 16, true, $largura);
    $conteudo .= "0.15 0.15 0.15 rg\n";
    $conteudo .= pdfLinhaCentralizada('CERTIFICADO DE PARTICIPAÇÃO', 450, 30, true, $largura);
    $conteudo .= pdfLinhaCentralizada('Certificamos que', 400, 14, false, $largura);
    $conteudo .= "0.753 0.090 0.176 rg\n";
    $conteudo .= pdfLinhaCentralizada((string) $certificado['participante_nome'], 362, 26, true, $largura);
    $conteudo .= "0.15 0.15 0.15 rg\n";

    $paragrafo = sprintf(
        'participou da atividade "%s" (%s), realizada em %s, das %s às %s, integrante do evento "%s", com carga horária de %s.',
        $certificado['atividade_titulo'],
        $certificado['atividade_tipo'],
        date('d/m/Y', $inicio),
        date('H\hi', $inicio),
        date('H\hi', $fim),
        $certificado['evento_titulo'],
        formatarCargaHoraria((string) $certificado['carga_horaria'])
    );
    $y = 320.0;
    foreach (pdfQuebrarLinhas($paragrafo, 14, 640) as $linha) {
        $conteudo .= pdfLinhaCentralizada($linha, $y, 14, false, $largura);
        $y -= 22;
    }

    $conteudo .= pdfLinhaCentralizada(
        'Ourinhos, ' . date('d/m/Y', strtotime((string) $certificado['emitido_em'])) . '.',
        max($y - 16, 170),
        12,
        false,
        $largura
    );
    $conteudo .= "q 0.6 0.6 0.6 RG 0.8 w 300 132 m 542 132 l S Q\n";
    $conteudo .= pdfLinhaCentralizada('Organização do evento', 116, 11, false, $largura);
    $conteudo .= "0.35 0.35 0.35 rg\n";
    $conteudo .= pdfLinhaCentralizada('Código de validação: ' . $codigo, 72, 11, true, $largura);
    $conteudo .= pdfLinhaCentralizada('Verifique a autenticidade em ' . $urlValidacao, 54, 9, false, $largura);

    $objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream",
        '<< /Title ' . pdfTexto('Certificado ' . $codigo) . ' /Producer (Eventeen) >>',
    ];

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $posicoes = [];
    foreach ($objetos as $indice => $objeto) {
        $posicoes[] = strlen($pdf);
        $pdf .= ($indice + 1) . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $inicioXref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach ($posicoes as $posicao) {
        $pdf .= sprintf("%010d 00000 n \n", $posicao);
    }
    $pdf .= 'trailer' . "\n<< /Size " . (count($objetos) + 1) . ' /Root 1 0 R /Info ' . count($objetos) . " 0 R >>\n";
    $pdf .= "startxref\n" . $inicioXref . "\n%%EOF\n";

    return $pdf;
}
