<?php

namespace App\Services;

use App\Models\Flyer;
use App\Models\FlyerPage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Turns an upload into flyer pages. Works with any storage disk (local or
 * S3-compatible): sources are copied to a temporary folder first.
 *
 * Full mode (GD available): PDFs are rasterised with pdftoppm and every page
 * gets WebP and JPEG copies at several widths.
 * Basic mode (no GD, e.g. serverless hosts): uploaded images are kept as they
 * are, with their dimensions read natively; PDFs are refused with a clear
 * message because they cannot be rasterised there.
 */
class FlyerImageProcessor
{
    private const IMAGE_TYPES = [
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    private ?ImageManager $images = null;

    public static function canResize(): bool
    {
        return config('qdealme.image_processing') !== 'basic'
            && extension_loaded('gd')
            && function_exists('imagewebp');
    }

    public static function canRasterizePdf(): bool
    {
        if (config('qdealme.image_processing') === 'basic') {
            return false;
        }

        $binary = (string) config('qdealme.pdftoppm_binary');

        return is_file($binary) || (new ExecutableFinder)->find($binary) !== null;
    }

    /**
     * Rasterise the upload into page images, create derivatives and replace
     * the flyer's pages. Idempotent: re-running rebuilds the same pages.
     */
    public function process(Flyer $flyer): void
    {
        $files = $this->sourceFiles($flyer);

        if ($files === []) {
            throw new RuntimeException('No upload found for this flyer.');
        }

        $work = rtrim(sys_get_temp_dir(), '/')."/qdealme-flyer-{$flyer->id}-".bin2hex(random_bytes(4));
        File::ensureDirectoryExists($work);

        try {
            $sources = [];

            foreach ($files as $i => $file) {
                $local = $work.'/source-'.$i.'.'.strtolower(pathinfo($file, PATHINFO_EXTENSION) ?: 'bin');
                $this->download($file, $local);

                $sources = [...$sources, ...($this->isPdf($local) ? $this->rasterizePdf($local, $work, $i) : [$local])];
            }

            $disk = Storage::disk('public');
            $directory = "flyers/{$flyer->id}/pages";
            $disk->deleteDirectory($directory);

            $flyer->pages()->delete();
            $flyer->forceFill(['cover_page_id' => null])->save();

            foreach ($sources as $index => $source) {
                $this->storePage($flyer, $source, $index + 1, $directory);
            }

            $flyer->refresh();
            $flyer->forceFill([
                'page_count' => count($sources),
                'cover_page_id' => $flyer->pages()->orderBy('number')->value('id'),
            ])->save();
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * @return array<int, string>
     */
    private function sourceFiles(Flyer $flyer): array
    {
        $files = $flyer->upload_files ?? [];

        if ($files === [] && $flyer->upload_path) {
            $files = [$flyer->upload_path];
        }

        return array_values(array_filter($files, fn ($f) => Storage::disk('local')->exists($f)));
    }

    private function download(string $file, string $target): void
    {
        $stream = Storage::disk('local')->readStream($file);

        if ($stream === null) {
            throw new RuntimeException("Could not read the uploaded file {$file}.");
        }

        $out = fopen($target, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($out);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function isPdf(string $path): bool
    {
        $handle = fopen($path, 'rb');
        $magic = (string) fread($handle, 5);
        fclose($handle);

        return $magic === '%PDF-';
    }

    /**
     * @return array<int, string>
     */
    private function rasterizePdf(string $pdf, string $work, int $index): array
    {
        if (! self::canRasterizePdf()) {
            throw new RuntimeException('PDF flyers cannot be processed on this server because pdftoppm (Poppler) is not installed. Upload the pages as JPG, PNG or WebP images instead.');
        }

        $prefix = $work.'/pdf-'.$index;

        $process = new Process([
            config('qdealme.pdftoppm_binary'),
            '-r', (string) config('qdealme.rasterize_dpi'),
            '-jpeg', '-jpegopt', 'quality=90',
            $pdf,
            $prefix,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('PDF rasterisation failed: '.trim($process->getErrorOutput()));
        }

        $pages = glob($prefix.'-*.jpg') ?: [];
        natsort($pages);

        if ($pages === []) {
            throw new RuntimeException('The PDF did not contain any pages.');
        }

        return array_values($pages);
    }

    private function storePage(Flyer $flyer, string $source, int $number, string $directory): void
    {
        self::canResize()
            ? $this->storeWithDerivatives($flyer, $source, $number, $directory)
            : $this->storeOriginalOnly($flyer, $source, $number, $directory);
    }

    private function storeWithDerivatives(Flyer $flyer, string $source, int $number, string $directory): void
    {
        $disk = Storage::disk('public');
        $this->images ??= new ImageManager(new Driver);

        $image = $this->images->read($source);
        $width = $image->width();
        $height = $image->height();

        $originalPath = "{$directory}/{$number}/original.jpg";
        $disk->put($originalPath, (string) $image->toJpeg(92));

        $derivatives = ['webp' => [], 'jpg' => []];

        foreach (config('qdealme.derivative_widths') as $target) {
            if ($target >= $width) {
                continue;
            }

            $scaled = clone $image;
            $scaled->scaleDown(width: $target);

            $disk->put("{$directory}/{$number}/{$target}.webp", (string) $scaled->toWebp(82));
            $disk->put("{$directory}/{$number}/{$target}.jpg", (string) $scaled->toJpeg(85));

            $derivatives['webp'][$target] = "{$directory}/{$number}/{$target}.webp";
            $derivatives['jpg'][$target] = "{$directory}/{$number}/{$target}.jpg";
        }

        // The original always acts as the largest candidate.
        $derivatives['jpg'][$width] = $originalPath;

        $this->createPage($flyer, $number, $originalPath, $width, $height, $derivatives);
    }

    private function storeOriginalOnly(Flyer $flyer, string $source, int $number, string $directory): void
    {
        $info = @getimagesize($source);

        if ($info === false || ! isset(self::IMAGE_TYPES[$info[2]])) {
            throw new RuntimeException('Unsupported flyer page. Upload JPG, PNG or WebP images.');
        }

        [$extension, $mime] = self::IMAGE_TYPES[$info[2]];
        $path = "{$directory}/{$number}/original.{$extension}";

        $stream = fopen($source, 'rb');
        Storage::disk('public')->put($path, $stream, ['ContentType' => $mime, 'visibility' => 'public']);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->createPage($flyer, $number, $path, (int) $info[0], (int) $info[1], []);
    }

    /**
     * @param  array<string, array<int, string>>  $derivatives
     */
    private function createPage(Flyer $flyer, int $number, string $path, int $width, int $height, array $derivatives): void
    {
        FlyerPage::query()->create([
            'flyer_id' => $flyer->id,
            'number' => $number,
            'original_path' => $path,
            'width' => $width,
            'height' => $height,
            'derivatives' => $derivatives,
        ]);
    }
}
