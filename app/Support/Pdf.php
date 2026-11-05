<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A small PDF writer with no dependencies, for the reports MediByte exports.
 *
 * It draws only what the reports need — flowing text, ruled tables, shaded
 * bands and page numbers — using the PDF core fonts (Helvetica) that every
 * reader already carries, so nothing has to be installed and no font file has
 * to be embedded. Coordinates are points, origin bottom-left, A4 portrait.
 */
class Pdf
{
    public const REGULAR = 'regular';

    public const BOLD = 'bold';

    public const ITALIC = 'italic';

    private const PAGE_WIDTH = 595.28;

    private const PAGE_HEIGHT = 841.89;

    private const TOP_MARGIN = 56;

    private const BOTTOM_MARGIN = 62;

    /** Height of the filled band that labels a table's columns. */
    private const HEADER_HEIGHT = 17;

    /** A section heading, its table header, and the first row beneath it. */
    private const HEADING_RESERVE = 76;

    /** Helvetica advance widths per 1000 units, indexed by Windows-1252 byte. */
    private const WIDTHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 722,
        80 => 667, 81 => 722, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
        111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
        118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
        125 => 334, 126 => 584,
    ];

    /** Helvetica-Bold advance widths per 1000 units. */
    private const BOLD_WIDTHS = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611,
        111 => 611, 112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611,
        118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280,
        125 => 389, 126 => 584,
    ];

    /** @var array<int, string> */
    private array $pages = [];

    private string $content = '';

    private float $y;

    private float $left = 48;

    private float $right = 547.28;

    /** @var array<int, string> */
    private array $meta;

    public function __construct(string $title, string $subject = '', string $author = '')
    {
        $this->meta = [
            'Title' => $title,
            'Subject' => $subject,
            'Author' => $author,
            'Creator' => 'MediByte',
            'Producer' => 'MediByte',
            'CreationDate' => self::pdfDate(now()),
        ];

        $this->y = self::PAGE_HEIGHT - self::TOP_MARGIN;
        $this->newPage();
    }

    public function usableWidth(): float
    {
        return $this->right - $this->left;
    }

    public function text(string $value, float $size = 10, string $style = self::REGULAR, ?float $x = null, string $align = 'left', ?float $width = null, float $leading = 13): void
    {
        $lines = $this->wrap($value, $size, $style, $width ?? $this->usableWidth());

        foreach ($lines as $line) {
            $this->ensureSpace($leading);

            $drawWidth = $this->stringWidth($line, $size, $style);
            $startX = $x ?? match ($align) {
                'center' => $this->left + (($width ?? $this->usableWidth()) - $drawWidth) / 2,
                'right' => $this->right - $drawWidth,
                default => $this->left,
            };

            $this->emit($this->textOperator($line, $startX, $this->y, $size, $style));
            $this->y -= $leading;
        }
    }

    /** Draw one line at an exact position without moving the flow cursor. */
    public function place(string $value, float $x, float $y, float $size = 10, string $style = self::REGULAR, string $color = '#171b26'): void
    {
        $this->emit($this->colorOperator($color).$this->textOperator($value, $x, $y, $size, $style));
    }

    /** A bold label at the left margin with its value right-aligned on the same line. */
    public function pair(string $label, string $value, float $size = 10): void
    {
        $this->ensureSpace(17);

        // The cursor marks the top of the line, so the baseline sits below it
        // and the text never touches the rule of the table above.
        $baseline = $this->y - 12;

        $this->emit($this->textOperator($label, $this->left, $baseline, $size, self::BOLD));
        $this->emit($this->textOperator($value, $this->right - $this->stringWidth($value, $size, self::REGULAR), $baseline, $size, self::REGULAR));

        $this->y -= 17;
    }

    public function keyValues(array $pairs, int $columns = 2, float $size = 9.5): void
    {
        $columnWidth = $this->usableWidth() / $columns;

        foreach (array_chunk($pairs, $columns) as $row) {
            $this->ensureSpace(26);

            foreach ($row as $index => [$label, $value]) {
                $x = $this->left + ($index * $columnWidth);

                $this->emit($this->textOperator(mb_strtoupper($label), $x, $this->y, 7, self::BOLD, '#7a8399'));
                $this->emit($this->textOperator($value, $x, $this->y - 11, $size, self::REGULAR, '#171b26'));
            }

            $this->y -= 26;
        }
    }

    /**
     * A ruled table that draws its own header band, so a table running past the
     * foot of a page carries its column labels onto the next one rather than
     * stranding them above an empty page.
     *
     * @param  array<int, array{label: string, width: float, align?: string}>  $columns
     * @param  array<int, array<int, string>>  $rows
     */
    public function table(array $columns, array $rows, float $size = 9, float $headerSize = 7.5): void
    {
        if ($rows === []) {
            return;
        }

        $offsets = [];
        $cursor = $this->left;

        foreach ($columns as $column) {
            $offsets[] = $cursor;
            $cursor += $column['width'];
        }

        $tableRight = $cursor;
        $layout = array_map(fn (array $row) => $this->rowLayout($row, $columns, $size), $rows);

        // A header with no row beneath it reads worse than one pushed forward.
        $this->ensureSpace(self::HEADER_HEIGHT + $layout[array_key_first($layout)]['height'] + 6);
        $this->drawHeader($columns, $offsets, $tableRight, $headerSize);

        foreach ($rows as $rowIndex => $row) {
            $cell = $layout[$rowIndex];

            if ($this->ensureSpace($cell['height'] + 2)) {
                $this->drawHeader($columns, $offsets, $tableRight, $headerSize);
            }

            $top = $this->y;
            $baseline = $this->y - 11;

            if ($rowIndex % 2 === 1) {
                $this->emit($this->colorOperator('#f2f6ff')."{$this->left} ".($top - $cell['height'] + 4).' '.($tableRight - $this->left).' '.($cell['height'] - 4)." re f\n");
            }

            foreach ($cell['lines'] as $index => $lines) {
                $columnWidth = $columns[$index]['width'] - 8;

                foreach ($lines as $lineIndex => $line) {
                    $drawWidth = $this->stringWidth($line, $size, self::REGULAR);
                    $x = ($columns[$index]['align'] ?? 'left') === 'right'
                        ? $offsets[$index] + $columnWidth - $drawWidth
                        : $offsets[$index] + 4;

                    $this->emit($this->textOperator($line, $x, $baseline - ($lineIndex * 11), $size, self::REGULAR, '#171b26'));
                }
            }

            $this->emit($this->colorOperator('#dbe3f3')."0.6 w\n{$this->left} ".($top - $cell['height'] + 4)." m {$tableRight} ".($top - $cell['height'] + 4)." l S\n");

            $this->y = $top - $cell['height'];
        }

        $this->y -= 6;
    }

    /**
     * @param  array<int, array{label: string, width: float, align?: string}>  $columns
     * @param  array<int, float>  $offsets
     * @return array{lines: array<int, array<int, string>>, height: float}
     */
    private function rowLayout(array $row, array $columns, float $size): array
    {
        $lines = [];
        $lineCount = 1;

        foreach ($row as $index => $cell) {
            $wrapped = $this->wrap((string) $cell, $size, self::REGULAR, $columns[$index]['width'] - 8);
            $lines[$index] = $wrapped;
            $lineCount = max($lineCount, count($wrapped));
        }

        return ['lines' => $lines, 'height' => 8 + ($lineCount * 11)];
    }

    /**
     * @param  array<int, array{label: string, width: float, align?: string}>  $columns
     * @param  array<int, float>  $offsets
     */
    private function drawHeader(array $columns, array $offsets, float $tableRight, float $size): void
    {
        $this->emit($this->colorOperator('#4f6fe0')."{$this->left} ".($this->y - self::HEADER_HEIGHT).' '.($tableRight - $this->left).' '.self::HEADER_HEIGHT." re f\n");

        foreach ($columns as $index => $column) {
            $label = mb_strtoupper($column['label']);
            $drawWidth = $this->stringWidth($label, $size, self::BOLD);
            $x = ($column['align'] ?? 'left') === 'right'
                ? $offsets[$index] + $column['width'] - $drawWidth - 4
                : $offsets[$index] + 4;

            $this->emit($this->textOperator($label, $x, $this->y - 12, $size, self::BOLD, '#ffffff'));
        }

        $this->y -= self::HEADER_HEIGHT + 4;
    }

    public function sectionHeading(string $value, string $color = '#3d5bc4'): void
    {
        $this->ensureSpace(self::HEADING_RESERVE);

        $this->y -= 12;

        $this->emit($this->textOperator(mb_strtoupper($value), $this->left, $this->y, 9, self::BOLD, $color));
        $this->emit($this->colorOperator('#e2e9f8')."0.8 w\n{$this->left} ".($this->y - 6)." m {$this->right} ".($this->y - 6)." l S\n");

        $this->y -= 16;
    }

    public function band(string $value, string $background = '#eef3ff', string $color = '#2f3c66', float $size = 9): void
    {
        $lines = $this->wrap($value, $size, self::REGULAR, $this->usableWidth() - 20);
        $height = 14 + (count($lines) * 12);

        $this->ensureSpace($height + 6);

        $this->emit($this->colorOperator($background)."{$this->left} ".($this->y - $height).' '.$this->usableWidth().' '.$height." re f\n");

        foreach ($lines as $index => $line) {
            $this->emit($this->textOperator($line, $this->left + 10, $this->y - 16 - ($index * 12), $size, self::REGULAR, $color));
        }

        $this->y -= $height + 8;
    }

    /** @param array<int, array{label: string, value: string, tone?: string}> $cards */
    public function summaryCards(array $cards, int $perRow = 4): void
    {
        $gap = 10;
        $cardWidth = ($this->usableWidth() - ($gap * ($perRow - 1))) / $perRow;
        $height = 52;

        foreach (array_chunk($cards, $perRow) as $row) {
            $this->ensureSpace($height + 10);

            foreach ($row as $index => $card) {
                $x = $this->left + ($index * ($cardWidth + $gap));

                $this->emit($this->colorOperator('#f6f9ff')."1 w\n{$x} ".($this->y - $height).' '.$cardWidth.' '.$height." re S\n");
                $this->emit($this->colorOperator($card['tone'] ?? '#4f6fe0')."2 w\n{$x} ".($this->y - $height).' '.$cardWidth.' '.$height." re S\n");
                $this->emit($this->textOperator(mb_strtoupper($card['label']), $x + 10, $this->y - 18, 7, self::BOLD, '#7a8399'));
                $this->emit($this->textOperator($card['value'], $x + 10, $this->y - 38, 13, self::BOLD, '#171b26'));
            }

            $this->y -= $height + 10;
        }
    }

    public function space(float $points = 8): void
    {
        $this->y -= $points;
    }

    public function pageBreak(): void
    {
        $this->closePage();
        $this->newPage();
    }

    /**
     * Assemble the document: footer with page numbers on every page, then the
     * object tree, cross-reference table and trailer.
     */
    public function output(string $footer = ''): string
    {
        $this->closePage();

        $count = count($this->pages);
        $streams = [];

        foreach ($this->pages as $index => $page) {
            $label = 'MediByte · Page '.($index + 1).' of '.$count;

            $page .= $this->colorOperator('#c9d5ef')."0.8 w\n48 46 m 547.28 46 l S\n";
            $page .= $this->textOperator($footer, 48, 33, 7.5, self::REGULAR, '#7a8399');
            $page .= $this->textOperator($label, 547.28 - $this->stringWidth($label, 7.5, self::REGULAR), 33, 7.5, self::REGULAR, '#7a8399');

            $streams[] = $page;
        }

        $objects = [];

        // 1 catalog, 2 pages, 3-5 fonts, then a page and its content per sheet.
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $firstPageObject = 6;

        foreach ($streams as $index => $stream) {
            $pageObject = $firstPageObject + ($index * 2);
            $contentObject = $pageObject + 1;

            $kids[] = $pageObject.' 0 R';

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /ProcSet [/PDF /Text] >> '
                .'/Contents '.$contentObject.' 0 R >>';

            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        $infoObject = count($objects) + 1;
        $info = '<<';
        foreach ($this->meta as $key => $value) {
            if ($value !== '') {
                $info .= ' /'.$key.' '.$this->literalString($value);
            }
        }
        $objects[$infoObject] = $info.' >>';

        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $total = $infoObject;
        $xref = strlen($pdf);

        $pdf .= "xref\n0 ".($total + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= $total; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size ".($total + 1).' /Root 1 0 R /Info '.$infoObject." 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function newPage(): void
    {
        $this->content = '';
        $this->y = self::PAGE_HEIGHT - self::TOP_MARGIN;
    }

    private function closePage(): void
    {
        $this->pages[] = $this->content;
    }

    private function emit(string $operator): void
    {
        $this->content .= $operator;
    }

    /** Breaks to a fresh page when the block will not fit, and says whether it did. */
    private function ensureSpace(float $needed): bool
    {
        if ($this->y - $needed >= self::BOTTOM_MARGIN) {
            return false;
        }

        $this->closePage();
        $this->newPage();

        return true;
    }

    private function textOperator(string $value, float $x, float $y, float $size, string $style, string $color = '#171b26'): string
    {
        $font = match ($style) {
            self::BOLD => '/F2',
            self::ITALIC => '/F3',
            default => '/F1',
        };

        return $this->colorOperator($color)
            .'BT '.$font.' '.$this->number($size).' Tf 1 0 0 1 '
            .$this->number($x).' '.$this->number($y).' Tm '.$this->literalString($value)." Tj ET\n";
    }

    /**
     * Bands are filled and rules are stroked, so one colour call sets both the
     * fill and the stroke operators and a shape never falls back to black.
     */
    private function colorOperator(string $hex): string
    {
        $components = $this->components($hex);

        return $components." rg\n".$components." RG\n";
    }

    private function components(string $hex): string
    {
        return $this->number(hexdec(substr($hex, 1, 2)) / 255).' '
            .$this->number(hexdec(substr($hex, 3, 2)) / 255).' '
            .$this->number(hexdec(substr($hex, 5, 2)) / 255);
    }

    /** @return array<int, string> */
    private function wrap(string $value, float $size, string $style, float $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->stringWidth($trial, $size, $style) > $maxWidth) {
                $lines[] = $current;
                $current = $word;

                continue;
            }

            $current = $trial;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function stringWidth(string $value, float $size, string $style): float
    {
        $table = $style === self::BOLD ? self::BOLD_WIDTHS : self::WIDTHS;
        $bytes = $this->toBytes($value);
        $total = 0;

        foreach ($bytes as $byte) {
            $total += $table[$byte] ?? ($byte > 126 ? 556 : 556);
        }

        return $total * $size / 1000;
    }

    /**
     * A PDF literal string: Windows-1252 bytes with the three characters that
     * would otherwise break the parser escaped.
     */
    private function literalString(string $value): string
    {
        $escaped = '';

        foreach ($this->toBytes($value) as $byte) {
            $character = chr($byte);

            if ($byte < 32) {
                // PDF string escapes are octal; a raw tab or newline would end
                // the literal early and corrupt the rest of the stream.
                $escaped .= '\\'.substr(sprintf('%03o', $byte), -3);

                continue;
            }

            if ($byte > 126) {
                $escaped .= $character;

                continue;
            }

            $escaped .= match ($character) {
                '(', ')', '\\' => '\\'.$character,
                default => $character,
            };
        }

        return '('.$escaped.')';
    }

    /** @return array<int, int> */
    private function toBytes(string $value): array
    {
        $encoded = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');

        if ($encoded === false) {
            $encoded = $value;
        }

        $bytes = [];
        $length = strlen($encoded);

        for ($index = 0; $index < $length; $index++) {
            $bytes[] = ord($encoded[$index]);
        }

        return $bytes;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function pdfDate(Carbon $moment): string
    {
        return 'D:'.$moment->format('YmdHis').$moment->format('O');
    }
}
