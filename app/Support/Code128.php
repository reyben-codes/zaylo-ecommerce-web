<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Code 128 (set B) barcodes for tracking numbers, drawn as SVG so the PDF renderer needs no extra library.
 */
class Code128
{
    /** Bar and space widths (in modules) for values 0–105, then the stop pattern. */
    public const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;
    private const STOP = 106;

    /** @return list<int> Alternating bar and space widths, starting with a bar. */
    public static function widths(string $text): array
    {
        if ($text === '' || preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException('Code 128 set B only encodes printable ASCII text.');
        }

        $values = array_map(fn (string $char) => ord($char) - 32, str_split($text));
        $checksum = self::START_B;
        foreach ($values as $position => $value) {
            $checksum += $value * ($position + 1);
        }

        $symbols = [self::START_B, ...$values, $checksum % 103, self::STOP];

        return array_map('intval', str_split(implode('', array_map(fn (int $symbol) => self::PATTERNS[$symbol], $symbols))));
    }

    /** The barcode as a standalone SVG with a quiet zone on both sides. */
    public static function svg(string $text, int $height = 60, int $module = 2): string
    {
        $quiet = 10 * $module;
        $x = $quiet;
        $bars = '';
        foreach (self::widths($text) as $index => $width) {
            if ($index % 2 === 0) {
                $bars .= sprintf('<rect x="%d" y="0" width="%d" height="%d"/>', $x, $width * $module, $height);
            }
            $x += $width * $module;
        }
        $total = $x + $quiet;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d"><rect width="%1$d" height="%2$d" fill="#fff"/><g fill="#000">%3$s</g></svg>',
            $total, $height, $bars,
        );
    }

    /** The SVG as a data URI for an <img> tag. */
    public static function dataUri(string $text, int $height = 60, int $module = 2): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $height, $module));
    }
}
