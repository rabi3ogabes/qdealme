<?php

namespace App\Services;

use App\Models\Flyer;
use App\Models\FlyerPage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Symfony\Component\Process\Process;

class FlyerImageProcessor
{
    private ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver);
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

        $disk = Storage::disk('public');
        $directory = "flyers/{$flyer->id}/pages";
        $disk->deleteDirectory($directory);
        $disk->makeDirectory($directory);

        $flyer->pages()->delete();
        $flyer->forceFill(['cover_page_id' => null])->save();

        $pageNumber = 0;

        foreach ($files as $file) {
            $absolute = Storage::disk('local')->path($file);

            $sources = str_ends_with(strtolower($file), '.pdf')
                ? $this->rasterizePdf($absolute, $flyer->id)
                : [$absolute];

            foreach ($sources as $source) {
                $pageNumber++;
                $this->storePage($flyer, $source, $pageNumber, $directory);
            }
        }

        $flyer->refresh();
        $flyer->forceFill([
            'page_count' => $pageNumber,
            'cover_page_id' => $flyer->pages()->orderBy('number')->value('id'),
        ])->save();
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

    /**
     * @return array<int, string>
     */
    private function rasterizePdf(string $pdf, int $flyerId): array
    {
        $out = storage_path("app/private/tmp/flyer-{$flyerId}");

        if (! is_dir($out)) {
            mkdir($out, 0775, true);
        }

        $process = new Process([
            config('qdealme.pdftoppm_binary'),
            '-r', (string) config('qdealme.rasterize_dpi'),
            '-jpeg', '-jpegopt', 'quality=90',
            $pdf,
            $out.'/page',
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('PDF rasterisation failed: '.trim($process->getErrorOutput()));
        }

        $pages = glob($out.'/page-*.jpg') ?: [];
        natsort($pages);

        return array_values($pages);
    }

    private function storePage(Flyer $flyer, string $source, int $number, string $directory): void
    {
        $disk = Storage::disk('public');
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

        FlyerPage::query()->create([
            'flyer_id' => $flyer->id,
            'number' => $number,
            'original_path' => $originalPath,
            'width' => $width,
            'height' => $height,
            'derivatives' => $derivatives,
        ]);
    }
}
