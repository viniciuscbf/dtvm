<?php
// MiniPDF — gerador de PDF em PHP puro (sem dependências), suficiente para relatórios tabulares.
// Gera PDF 1.4 com Helvetica/Helvetica-Bold, texto, linhas e retângulos, multipágina A4.

class MiniPDF {
    private array $paginas = [];
    private float $w = 595.28, $h = 841.89;   // A4 em pontos
    private float $y;
    private float $margem = 46;

    public function __construct() { $this->novaPagina(); }

    public function novaPagina(): void {
        $this->paginas[] = '';
        $this->y = $this->h - $this->margem;
    }

    public function largura(): float { return $this->w; }
    public function margem(): float { return $this->margem; }
    public function yAtual(): float { return $this->y; }

    /** Desce o cursor; quebra página automaticamente. */
    public function avancar(float $dy): void {
        $this->y -= $dy;
        if ($this->y < $this->margem + 20) $this->novaPagina();
    }

    private function esc(string $s): string {
        $conv = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($conv === false) $conv = preg_replace('/[^\x20-\x7E]/', '?', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $conv);
    }

    private function add(string $op): void {
        $this->paginas[count($this->paginas) - 1] .= $op . "\n";
    }

    /** Texto em posição absoluta (y em coordenadas do cursor: topo→baixo já tratado pelo chamador). */
    public function texto(float $x, float $y, float $tam, string $txt, bool $bold = false, array $cor = [0, 0, 0], string $alinh = 'L', float $largCol = 0): void {
        if ($alinh !== 'L') {
            $larg = $this->larguraTexto($txt, $tam, $bold);
            $x = $alinh === 'R' ? $x + $largCol - $larg : $x + ($largCol - $larg) / 2;
        }
        [$r, $g, $b] = $cor;
        $this->add(sprintf('BT /F%d %.1f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET',
            $bold ? 2 : 1, $tam, $r, $g, $b, $x, $y, $this->esc($txt)));
    }

    /** Escreve na linha corrente (usa o cursor interno). */
    public function linhaTexto(array $colunas, float $tam = 8.5, bool $bold = false): void {
        foreach ($colunas as [$x, $txt, $alinh, $largCol, $cor]) {
            $this->texto($x, $this->y, $tam, $txt, $bold, $cor ?? [0, 0, 0], $alinh ?? 'L', $largCol ?? 0);
        }
        $this->avancar($tam + 4.5);
    }

    public function regua(float $x1, float $x2, ?float $y = null, float $esp = 0.6, array $cor = [0.75, 0.78, 0.82]): void {
        $y = $y ?? $this->y + 3;
        [$r, $g, $b] = $cor;
        $this->add(sprintf('%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S', $r, $g, $b, $esp, $x1, $y, $x2, $y));
    }

    public function faixa(float $x, float $largura, float $altura, array $cor): void {
        [$r, $g, $b] = $cor;
        $this->add(sprintf('%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f', $r, $g, $b, $x, $this->y - 3, $largura, $altura));
    }

    /** Aproximação de largura (Helvetica ~0.5 em média por caractere). */
    public function larguraTexto(string $txt, float $tam, bool $bold = false): float {
        return strlen($this->esc($txt)) * $tam * ($bold ? 0.53 : 0.5);
    }

    /** Monta o arquivo PDF completo (xref calculado). */
    public function saida(): string {
        $objetos = [];
        $nPags = count($this->paginas);
        $kids = [];
        for ($i = 0; $i < $nPags; $i++) $kids[] = (5 + $i * 2) . ' 0 R';
        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objetos[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $nPags >>";
        $objetos[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objetos[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        foreach ($this->paginas as $i => $conteudo) {
            $pagObj = 5 + $i * 2;
            $contObj = $pagObj + 1;
            $objetos[$pagObj] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $this->w, $this->h, $contObj);
            $objetos[$contObj] = "<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream";
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $max = max(array_keys($objetos));
        for ($n = 1; $n <= $max; $n++) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n" . $objetos[$n] . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $max; $n++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}

/**
 * PDF da carteira segregada em seções por classe de ativo.
 * $ativos: linhas de carteira() (com valor_mercado/custo/resultado calculados).
 */
function pdf_carteira(array $fundo, array $ativos, string $data, string $geradoPor): string {
    $pdf = new MiniPDF();
    $m = $pdf->margem();
    $L = $pdf->largura() - $m;
    $fmt = fn($v, $d = 2) => number_format((float)$v, $d, ',', '.');

    // cabeçalho
    $pdf->faixa($m - 10, $L - $m + 20, 4, [0.79, 0.64, 0.15]);
    $pdf->avancar(14);
    $pdf->linhaTexto([[$m, 'CARTEIRA DO FUNDO', 'L', 0, [0.06, 0.10, 0.17]]], 15, true);
    $pdf->linhaTexto([[$m, $fundo['nome'], 'L', 0, [0.06, 0.10, 0.17]]], 11.5, true);
    $pdf->linhaTexto([[$m, 'CNPJ ' . $fundo['cnpj'] . '  ·  ' . $fundo['classe'] . '  ·  Benchmark ' . $fundo['benchmark'], 'L', 0, [0.4, 0.44, 0.5]]], 8.5);
    $pdf->linhaTexto([[$m, 'Posição de ' . date('d/m/Y', strtotime($data)) . '  ·  gerado em ' . date('d/m/Y H:i') . ' por ' . $geradoPor, 'L', 0, [0.4, 0.44, 0.5]]], 8.5);
    $pdf->avancar(8);

    // colunas: código X, qtd, preço médio, preço MaM, valor, %PL, resultado
    $cX = $m; $cQtd = 200; $cPM = 265; $cMaM = 330; $cVal = 415; $cPct = 470; $cRes = 549;
    $pl = (float)$fundo['pl_atual'] > 0 ? (float)$fundo['pl_atual']
        : array_sum(array_column($ativos, 'valor_mercado')) + (float)$fundo['caixa_atual'];

    // agrupa por classe
    $grupos = [];
    foreach ($ativos as $a) $grupos[$a['tipo']][] = $a;
    uasort($grupos, fn($a, $b) => array_sum(array_column($b, 'valor_mercado')) <=> array_sum(array_column($a, 'valor_mercado')));

    $cab = function () use ($pdf, $cX, $cQtd, $cPM, $cMaM, $cVal, $cPct, $cRes, $m, $L) {
        $cinza = [0.42, 0.46, 0.52];
        $pdf->linhaTexto([
            [$cX, 'ATIVO', 'L', 0, $cinza], [$cQtd - 55, 'QTDE', 'R', 55, $cinza],
            [$cPM - 60, 'PREÇO MÉDIO', 'R', 60, $cinza], [$cMaM - 60, 'PREÇO MAM', 'R', 60, $cinza],
            [$cVal - 80, 'VALOR (R$)', 'R', 80, $cinza], [$cPct - 45, '% PL', 'R', 45, $cinza],
            [$cRes - 72, 'RESULTADO', 'R', 72, $cinza],
        ], 7, true);
        $pdf->regua($m, $L);
        $pdf->avancar(3);
    };

    $totalGeral = 0.0;
    foreach ($grupos as $classe => $itens) {
        $subtotal = array_sum(array_column($itens, 'valor_mercado'));
        $totalGeral += $subtotal;
        // título da seção
        $pdf->avancar(6);
        $pdf->faixa($m - 4, $L - $m + 8, 15, [0.92, 0.94, 0.96]);
        $pdf->linhaTexto([
            [$cX, mb_strtoupper($classe), 'L', 0, [0.06, 0.10, 0.17]],
            [$cRes - 160, $fmt($subtotal) . '  (' . $fmt($subtotal / $pl * 100, 1) . '% do PL)', 'R', 160, [0.06, 0.10, 0.17]],
        ], 9.5, true);
        $cab();
        foreach ($itens as $a) {
            $corRes = $a['resultado'] >= 0 ? [0.09, 0.50, 0.24] : [0.72, 0.11, 0.11];
            $pdf->linhaTexto([
                [$cX, $a['codigo'] . ($a['fonte_preco'] === 'Comitê' ? ' *' : ''), 'L', 0, null],
                [$cQtd - 55, $fmt($a['quantidade'], 0), 'R', 55, null],
                [$cPM - 60, $fmt($a['preco_medio'], 4), 'R', 60, null],
                [$cMaM - 60, $fmt($a['preco_mam'], 4), 'R', 60, null],
                [$cVal - 80, $fmt($a['valor_mercado']), 'R', 80, null],
                [$cPct - 45, $fmt($a['valor_mercado'] / $pl * 100, 2), 'R', 45, null],
                [$cRes - 72, $fmt($a['resultado']), 'R', 72, $corRes],
            ], 8.5);
        }
    }

    // totais
    $pdf->avancar(8);
    $pdf->regua($m, $L, null, 1.1, [0.06, 0.10, 0.17]);
    $pdf->avancar(6);
    $caixa = (float)$fundo['caixa_atual'];
    $pdf->linhaTexto([[$cX, 'Total em ativos', 'L', 0, null], [$cRes - 120, $fmt($totalGeral), 'R', 120, null]], 9.5, true);
    $pdf->linhaTexto([[$cX, 'Caixa e disponibilidades', 'L', 0, null], [$cRes - 120, $fmt($caixa), 'R', 120, null]], 9.5);
    $pdf->linhaTexto([[$cX, 'PATRIMÔNIO LÍQUIDO', 'L', 0, [0.06, 0.10, 0.17]], [$cRes - 120, $fmt($totalGeral + $caixa), 'R', 120, [0.06, 0.10, 0.17]]], 10.5, true);
    $pdf->avancar(12);
    $pdf->linhaTexto([[$cX, '* ativo precificado por comitê (sem fonte primária líquida).', 'L', 0, [0.45, 0.48, 0.53]]], 7.5);
    $pdf->linhaTexto([[$cX, 'Documento gerado pela plataforma da administradora — piloto com dados simulados. Não é documento oficial.', 'L', 0, [0.45, 0.48, 0.53]]], 7.5);

    return $pdf->saida();
}
