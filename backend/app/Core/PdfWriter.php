<?php

declare(strict_types=1);

namespace Prometheus\Core;

/**
 * Generatore PDF minimale puro PHP senza dipendenze esterne.
 * Produce documenti A4 con intestazione, testo semplice e tabelle.
 */
final class PdfWriter
{
    private const PAGE_W = 595.28;   // A4 larghezza pt
    private const PAGE_H = 841.89;   // A4 altezza pt
    private const MARGIN  = 40.0;    // margine sinistro/destro/alto/basso

    private string $buffer = '';
    private array  $objects = [];
    private array  $offsets = [];
    private int    $objCount = 0;

    private float $cursorY;
    private float $pageContentStart = 0.0;
    private int   $pageStreamObj = 0;
    private string $pageContent = '';
    private array $pageObjects = [];

    public function __construct()
    {
        $this->cursorY = self::PAGE_H - self::MARGIN;
        $this->buffer  = "%PDF-1.4\n";
    }

    // ── Oggetti PDF ───────────────────────────────────────────────────

    private function addObj(string $dict, string $stream = ''): int
    {
        $this->objCount++;
        $n = $this->objCount;

        if ($stream !== '') {
            $this->objects[$n] = $dict . "\nstream\n" . $stream . "\nendstream";
        } else {
            $this->objects[$n] = $dict;
        }

        return $n;
    }

    // ── Encoding helper ───────────────────────────────────────────────

    private function enc(string $text): string
    {
        // Converte UTF-8 in latin-1 per PDF standard encoding.
        $out = '';

        for ($i = 0, $len = mb_strlen($text, 'UTF-8'); $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $ord  = mb_ord($char, 'UTF-8');

            if ($ord < 256) {
                $out .= chr($ord);
            } else {
                $out .= '?';
            }
        }

        return $out;
    }

    private function pdfStr(string $text): string
    {
        $encoded = $this->enc($text);

        return '(' . addcslashes($encoded, '()\\') . ')';
    }

    // ── Stream di pagina ─────────────────────────────────────────────

    private function ps(string $cmd): void
    {
        $this->pageContent .= $cmd . "\n";
    }

    private function text(float $x, float $y, string $txt, float $size = 10.0, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $this->ps("BT {$font} {$size} Tf {$x} {$y} Td " . $this->pdfStr($txt) . " Tj ET");
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.5): void
    {
        $this->ps("{$w} w {$x1} {$y1} m {$x2} {$y2} l S");
    }

    private function rect(float $x, float $y, float $w, float $h, bool $fill = false): void
    {
        $op = $fill ? 'f' : 'S';
        $this->ps("0.85 0.90 0.95 rg {$x} {$y} {$w} {$h} re {$op} 0 0 0 rg");
    }

    // ── Impaginazione ────────────────────────────────────────────────

    private function checkBreak(float $needed = 20.0): void
    {
        if ($this->cursorY - $needed < self::MARGIN) {
            $this->endPage();
            $this->startPage();
        }
    }

    private function startPage(): void
    {
        $this->pageContent = '';
        $this->cursorY     = self::PAGE_H - self::MARGIN;
    }

    private function endPage(): void
    {
        $streamObj = $this->addObj(
            "<< /Length " . strlen($this->pageContent) . " >>",
            $this->pageContent
        );
        $pageObj = $this->addObj(
            "<< /Type /Page /Parent 2 0 R"
            . " /MediaBox [0 0 " . self::PAGE_W . " " . self::PAGE_H . "]"
            . " /Contents {$streamObj} 0 R"
            . " /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> >>"
        );
        $this->pageObjects[] = $pageObj;
    }

    // ── API pubblica ─────────────────────────────────────────────────

    public function title(string $text): void
    {
        $this->checkBreak(30);
        $this->text(self::MARGIN, $this->cursorY, $text, 14.0, true);
        $this->cursorY -= 6;
        $this->line(self::MARGIN, $this->cursorY, self::PAGE_W - self::MARGIN, $this->cursorY, 1.0);
        $this->cursorY -= 14;
    }

    public function subtitle(string $text): void
    {
        $this->checkBreak(20);
        $this->text(self::MARGIN, $this->cursorY, $text, 11.0, true);
        $this->cursorY -= 14;
    }

    public function paragraph(string $label, string $value): void
    {
        $this->checkBreak(14);
        $this->text(self::MARGIN, $this->cursorY, $label . ':', 9.0, true);
        $this->text(self::MARGIN + 120, $this->cursorY, $value, 9.0);
        $this->cursorY -= 12;
    }

    public function spacer(float $pt = 8.0): void
    {
        $this->cursorY -= $pt;
    }

    /**
     * @param string[]   $headers   Intestazioni colonne
     * @param array[]    $rows      Righe dati
     * @param float[]    $widths    Larghezze colonne in pt (somma <= area stampabile)
     */
    public function table(array $headers, array $rows, array $widths): void
    {
        $rowH  = 14.0;
        $this->checkBreak($rowH + 4);

        $x0 = self::MARGIN;

        // Intestazione tabella
        $this->rect($x0, $this->cursorY - $rowH + 3, array_sum($widths), $rowH, true);
        $x = $x0;

        foreach ($headers as $i => $header) {
            $this->text($x + 3, $this->cursorY - 8, $header, 8.5, true);
            $x += $widths[$i];
        }

        $this->cursorY -= $rowH;

        // Righe
        foreach ($rows as $row) {
            $this->checkBreak($rowH + 2);
            $x = $x0;

            foreach (array_values($row) as $i => $cell) {
                $cellText = $this->truncate((string) ($cell ?? ''), (int) (($widths[$i] - 6) / 5));
                $this->text($x + 3, $this->cursorY - 8, $cellText, 7.5);
                $x += $widths[$i];
            }

            // linea separatrice
            $this->line($x0, $this->cursorY - $rowH + 3, $x0 + array_sum($widths), $this->cursorY - $rowH + 3, 0.3);
            $this->cursorY -= $rowH;
        }

        // bordo tabella
        $tableH = ($rowH * (count($rows) + 1));
        $yTop   = $this->cursorY + $tableH;
        $this->line($x0, $yTop + 3, $x0, $this->cursorY + 3, 0.5);
        $this->line($x0 + array_sum($widths), $yTop + 3, $x0 + array_sum($widths), $this->cursorY + 3, 0.5);
    }

    private function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $maxChars - 1)) . '…';
    }

    // ── Generazione documento ────────────────────────────────────────

    public function render(): string
    {
        // Chiude l'ultima pagina
        $this->endPage();

        // Catalog e Pages sono riservati a obj 1 e 2; font a 3,4,5
        // Li costruiamo dopo aver raccolto tutti i page obj
        $pagesRef = implode(' 0 R ', $this->pageObjects) . ' 0 R';
        $pageCount = count($this->pageObjects);

        $pagesDict = "<< /Type /Pages /Kids [{$pagesRef}] /Count {$pageCount} >>";

        // Riordina oggetti: 1=catalog, 2=pages, 3=info, 4=font Helvetica, 5=font Helvetica-Bold
        $ordered = [];
        $ordered[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $ordered[2] = $pagesDict;
        $ordered[3] = "<< /Producer (Prometheus2) /CreationDate (D:" . date('YmdHis') . ") >>";
        $ordered[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $ordered[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // Shifta gli oggetti interni di +5 (erano numerati da 1)
        $shifted = [];

        foreach ($this->objects as $n => $body) {
            $shifted[$n + 5] = $body;
        }

        $all = $ordered + $shifted;
        ksort($all);

        $out     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($all as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= "{$n} 0 obj\n{$body}\nendobj\n";
        }

        // Cross-reference table
        $xrefOffset = strlen($out);
        $out .= "xref\n0 " . (count($all) + 1) . "\n";
        $out .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $out .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $out .= "trailer\n<< /Size " . (count($all) + 1) . " /Root 1 0 R /Info 3 0 R >>\n";
        $out .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $out;
    }
}
