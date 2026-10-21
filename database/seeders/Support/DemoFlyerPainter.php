<?php

namespace Database\Seeders\Support;

use GdImage;
use RuntimeException;

/**
 * Paints realistic-looking flyer pages for the fictional demo stores, so a
 * fresh install has something beautiful to browse without using any real
 * retailer's artwork. Each product tile's position is returned as a
 * normalised bounding box, which becomes the offer hotspot.
 */
final class DemoFlyerPainter
{
    public const WIDTH = 1080;

    public const HEIGHT = 1440;

    private const HEADER = 230;

    private const FOOTER = 90;

    private const PAD = 36;

    private const GAP = 24;

    private string $regular;

    private string $bold;

    public function __construct()
    {
        $this->regular = __DIR__.'/../assets/ReadexPro-400.ttf';
        $this->bold = __DIR__.'/../assets/ReadexPro-700.ttf';

        if (! is_file($this->bold)) {
            throw new RuntimeException('Demo font missing: '.$this->bold);
        }
    }

    /**
     * @param  array{store: string, title: string, validity: string, color: string, accent: string, page: int, pages: int}  $meta
     * @param  array<int, array{name: string, size: string, price: float, old_price: float|null, shape: string}>  $products  up to 6
     * @return array{path: string, boxes: array<int, array{x: float, y: float, w: float, h: float}>}
     */
    public function paint(string $path, array $meta, array $products): array
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($im, true);

        [$r, $g, $b] = $this->hex($meta['color']);
        $brand = imagecolorallocate($im, $r, $g, $b);
        $brandDark = imagecolorallocate($im, (int) ($r * 0.7), (int) ($g * 0.7), (int) ($b * 0.7));
        [$ar, $ag, $ab] = $this->hex($meta['accent']);
        $accent = imagecolorallocate($im, $ar, $ag, $ab);
        $white = imagecolorallocate($im, 255, 255, 255);
        $paper = imagecolorallocate($im, 250, 247, 242);
        $ink = imagecolorallocate($im, 33, 28, 30);
        $muted = imagecolorallocate($im, 128, 118, 110);
        $line = imagecolorallocate($im, 225, 218, 208);

        // Background and header band with a soft diagonal pattern.
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $paper);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEADER, $brand);
        for ($i = -self::HEADER; $i < self::WIDTH; $i += 46) {
            imageline($im, $i, self::HEADER, $i + self::HEADER, 0, $brandDark);
        }

        $this->text($im, 52, $this->bold, $white, self::PAD + 6, 96, $meta['store']);
        $this->text($im, 30, $this->regular, $white, self::PAD + 6, 150, $meta['title']);
        $this->pill($im, self::PAD + 6, 172, $meta['validity'], 20, $accent, $ink);

        $pageLabel = $meta['page'].' / '.$meta['pages'];
        $box = imagettfbbox(22, 0, $this->bold, $pageLabel);
        $this->text($im, 22, $this->bold, $white, self::WIDTH - self::PAD - ($box[2] - $box[0]) - 6, 96, $pageLabel);

        // Product grid: 2 columns x 3 rows.
        $cols = 2;
        $rows = 3;
        $gridTop = self::HEADER + self::GAP;
        $gridHeight = self::HEIGHT - $gridTop - self::FOOTER - self::GAP;
        $tileW = (int) ((self::WIDTH - 2 * self::PAD - ($cols - 1) * self::GAP) / $cols);
        $tileH = (int) (($gridHeight - ($rows - 1) * self::GAP) / $rows);

        $boxes = [];

        foreach (array_values(array_slice($products, 0, $cols * $rows)) as $i => $product) {
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $x = self::PAD + $col * ($tileW + self::GAP);
            $y = $gridTop + $row * ($tileH + self::GAP);

            $this->roundedRect($im, $x, $y, $x + $tileW, $y + $tileH, 22, $white);
            imagerectangle($im, $x, $y, $x + $tileW, $y + $tileH, $paper);

            $this->illustration($im, $product['shape'], $x + 26, $y + 26, (int) ($tileW * 0.42), $tileH - 52, $brand, $accent, $line);

            $tx = $x + (int) ($tileW * 0.48);
            $tw = $tileW - (int) ($tileW * 0.48) - 22;
            $lines = $this->wrap($product['name'], 24, $this->bold, $tw, 3);
            $ty = $y + 58;
            foreach ($lines as $l) {
                $this->text($im, 24, $this->bold, $ink, $tx, $ty, $l);
                $ty += 34;
            }
            $this->text($im, 19, $this->regular, $muted, $tx, $ty + 4, $product['size']);

            if ($product['old_price']) {
                $old = 'QAR '.$this->money($product['old_price']);
                $this->text($im, 21, $this->regular, $muted, $tx, $y + $tileH - 120, $old);
                $ob = imagettfbbox(21, 0, $this->regular, $old);
                imagesetthickness($im, 3);
                imageline($im, $tx, $y + $tileH - 128, $tx + ($ob[2] - $ob[0]), $y + $tileH - 128, $brand);
                imagesetthickness($im, 1);
            }

            $priceText = $this->money($product['price']);
            $pb = imagettfbbox(44, 0, $this->bold, $priceText);
            $pw = ($pb[2] - $pb[0]) + 92;
            $this->roundedRect($im, $tx - 6, $y + $tileH - 96, $tx - 6 + $pw, $y + $tileH - 26, 20, $accent);
            $this->text($im, 18, $this->bold, $ink, $tx + 10, $y + $tileH - 52, 'QAR');
            $this->text($im, 44, $this->bold, $ink, $tx + 66, $y + $tileH - 42, $priceText);

            if ($product['old_price'] && $product['old_price'] > $product['price']) {
                $pct = (int) round(($product['old_price'] - $product['price']) / $product['old_price'] * 100);
                $this->badge($im, $x + 10, $y + 10, '-'.$pct.'%', $brand, $white);
            }

            $boxes[] = [
                'x' => round($x / self::WIDTH, 4),
                'y' => round($y / self::HEIGHT, 4),
                'w' => round($tileW / self::WIDTH, 4),
                'h' => round($tileH / self::HEIGHT, 4),
            ];
        }

        // Footer.
        imagefilledrectangle($im, 0, self::HEIGHT - self::FOOTER, self::WIDTH, self::HEIGHT, $ink);
        $this->text($im, 18, $this->regular, $white, self::PAD + 6, self::HEIGHT - 38, 'While stocks last. Prices in Qatari Riyal. Demo flyer for QDealMe.');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        imagejpeg($im, $path, 88);
        imagedestroy($im);

        return ['path' => $path, 'boxes' => $boxes];
    }

    /**
     * Square brand logo as SVG markup.
     */
    public static function logoSvg(string $initials, string $color, string $accent): string
    {
        $initials = htmlspecialchars($initials, ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" width="160" height="160">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$color}"/><stop offset="1" stop-color="{$color}" stop-opacity=".82"/></linearGradient></defs>
  <rect width="160" height="160" rx="36" fill="url(#g)"/>
  <circle cx="128" cy="32" r="12" fill="{$accent}"/>
  <text x="80" y="102" text-anchor="middle" font-family="Readex Pro, Arial, sans-serif" font-size="58" font-weight="700" fill="#fff" letter-spacing="-1">{$initials}</text>
</svg>
SVG;
    }

    private function illustration(GdImage $im, string $shape, int $x, int $y, int $w, int $h, int $brand, int $accent, int $line): void
    {
        $soft = imagecolorallocatealpha($im, 0, 0, 0, 120);
        $cx = $x + intdiv($w, 2);
        $cy = $y + intdiv($h, 2);
        $s = min($w, $h);

        $this->roundedRect($im, $x, $y, $x + $w, $y + $h, 18, $line);

        switch ($shape) {
            case 'bottle':
                $this->roundedRect($im, $cx - (int) ($s * 0.22), $cy - (int) ($s * 0.18), $cx + (int) ($s * 0.22), $cy + (int) ($s * 0.42), 22, $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.09), $cy - (int) ($s * 0.36), $cx + (int) ($s * 0.09), $cy - (int) ($s * 0.16), $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.11), $cy - (int) ($s * 0.42), $cx + (int) ($s * 0.11), $cy - (int) ($s * 0.34), $accent);
                imagefilledrectangle($im, $cx - (int) ($s * 0.22), $cy + (int) ($s * 0.02), $cx + (int) ($s * 0.22), $cy + (int) ($s * 0.18), $accent);
                break;
            case 'box':
                $this->roundedRect($im, $cx - (int) ($s * 0.3), $cy - (int) ($s * 0.32), $cx + (int) ($s * 0.3), $cy + (int) ($s * 0.36), 14, $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.3), $cy - (int) ($s * 0.08), $cx + (int) ($s * 0.3), $cy + (int) ($s * 0.1), $accent);
                imagefilledellipse($im, $cx, $cy + (int) ($s * 0.24), (int) ($s * 0.2), (int) ($s * 0.08), $soft);
                break;
            case 'round':
                imagefilledellipse($im, $cx, $cy + (int) ($s * 0.04), (int) ($s * 0.62), (int) ($s * 0.6), $brand);
                imagefilledellipse($im, $cx - (int) ($s * 0.1), $cy - (int) ($s * 0.08), (int) ($s * 0.16), (int) ($s * 0.12), $soft);
                imagefilledellipse($im, $cx + (int) ($s * 0.06), $cy - (int) ($s * 0.3), (int) ($s * 0.2), (int) ($s * 0.1), $accent);
                break;
            case 'screen':
                $this->roundedRect($im, $cx - (int) ($s * 0.42), $cy - (int) ($s * 0.3), $cx + (int) ($s * 0.42), $cy + (int) ($s * 0.2), 10, $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.38), $cy - (int) ($s * 0.26), $cx + (int) ($s * 0.38), $cy + (int) ($s * 0.16), $accent);
                imagefilledrectangle($im, $cx - (int) ($s * 0.04), $cy + (int) ($s * 0.2), $cx + (int) ($s * 0.04), $cy + (int) ($s * 0.3), $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.18), $cy + (int) ($s * 0.3), $cx + (int) ($s * 0.18), $cy + (int) ($s * 0.34), $brand);
                break;
            case 'phone':
                $this->roundedRect($im, $cx - (int) ($s * 0.2), $cy - (int) ($s * 0.4), $cx + (int) ($s * 0.2), $cy + (int) ($s * 0.4), 22, $brand);
                $this->roundedRect($im, $cx - (int) ($s * 0.16), $cy - (int) ($s * 0.34), $cx + (int) ($s * 0.16), $cy + (int) ($s * 0.32), 14, $accent);
                imagefilledellipse($im, $cx, $cy - (int) ($s * 0.37), 10, 10, $soft);
                break;
            case 'appliance':
                $this->roundedRect($im, $cx - (int) ($s * 0.3), $cy - (int) ($s * 0.38), $cx + (int) ($s * 0.3), $cy + (int) ($s * 0.4), 24, $brand);
                imagefilledellipse($im, $cx, $cy + (int) ($s * 0.06), (int) ($s * 0.36), (int) ($s * 0.36), $accent);
                imagefilledellipse($im, $cx, $cy + (int) ($s * 0.06), (int) ($s * 0.2), (int) ($s * 0.2), $brand);
                imagefilledrectangle($im, $cx - (int) ($s * 0.2), $cy - (int) ($s * 0.3), $cx + (int) ($s * 0.2), $cy - (int) ($s * 0.24), $accent);
                break;
            default:
                $this->roundedRect($im, $cx - (int) ($s * 0.32), $cy - (int) ($s * 0.24), $cx + (int) ($s * 0.32), $cy + (int) ($s * 0.36), 30, $brand);
                imagefilledellipse($im, $cx, $cy - (int) ($s * 0.24), (int) ($s * 0.34), (int) ($s * 0.2), $accent);
        }
    }

    private function roundedRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = max(0, min($r, intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2)));
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function pill(GdImage $im, int $x, int $y, string $label, int $size, int $bg, int $fg): void
    {
        $b = imagettfbbox($size, 0, $this->bold, $label);
        $w = ($b[2] - $b[0]) + 36;
        $this->roundedRect($im, $x, $y, $x + $w, $y + $size + 22, intdiv($size + 22, 2), $bg);
        $this->text($im, $size, $this->bold, $fg, $x + 18, $y + $size + 10, $label);
    }

    private function badge(GdImage $im, int $x, int $y, string $label, int $bg, int $fg): void
    {
        imagefilledellipse($im, $x + 28, $y + 28, 76, 76, $bg);
        $b = imagettfbbox(18, 0, $this->bold, $label);
        $this->text($im, 18, $this->bold, $fg, $x + 28 - intdiv($b[2] - $b[0], 2), $y + 36, $label);
    }

    private function text(GdImage $im, int $size, string $font, int $color, int $x, int $y, string $text): void
    {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, int $size, string $font, int $width, int $maxLines): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $b = imagettfbbox($size, 0, $font, $candidate);

            if (($b[2] - $b[0]) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '.').'…';
        }

        return $lines;
    }

    private function money(float $amount): string
    {
        return floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
