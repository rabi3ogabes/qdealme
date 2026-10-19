<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class FlyerProcessingTest extends TestCase
{
    private function jpeg(int $w = 900, int $h = 1200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 120, 30, 60));
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Minimal valid PDF with one JPEG per page (DCTDecode), no external tools.
     */
    private function pdf(int $pages): string
    {
        $jpeg = $this->jpeg(600, 800);
        $objects = [];
        $kids = [];
        $n = 3;

        for ($i = 0; $i < $pages; $i++) {
            $page = $n++;
            $content = $n++;
            $image = $n++;
            $kids[] = "{$page} 0 R";
            $stream = 'q 600 0 0 800 0 0 cm /Im0 Do Q';
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Resources << /XObject << /Im0 {$image} 0 R >> >> /Contents {$content} 0 R >>";
            $objects[$content] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$image] = '<< /Type /XObject /Subtype /Image /Width 600 /Height 800 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n{$jpeg}\nendstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';
        ksort($objects);

        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }

        return $out.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function draftFlyer(array $files): Flyer
    {
        $flyer = $this->makeFlyer(['status' => FlyerStatus::Draft], null, 0);
        $flyer->forceFill(['upload_files' => $files])->save();

        return $flyer;
    }

    public function test_image_uploads_become_pages_with_derivatives(): void
    {
        Storage::disk('local')->put('uploads/flyers/a.jpg', $this->jpeg());
        Storage::disk('local')->put('uploads/flyers/b.jpg', $this->jpeg());
        $flyer = $this->draftFlyer(['uploads/flyers/a.jpg', 'uploads/flyers/b.jpg']);

        ProcessFlyerUpload::dispatchSync($flyer->id);

        $flyer->refresh();
        $this->assertSame(FlyerStatus::InReview, $flyer->status);
        $this->assertSame(2, $flyer->page_count);
        $this->assertNotNull($flyer->cover_page_id);

        $page = $flyer->pages()->first();
        $this->assertSame(900, $page->width);
        $this->assertArrayHasKey(320, $page->derivatives['webp']);
        $this->assertArrayHasKey(640, $page->derivatives['webp']);
        Storage::disk('public')->assertExists($page->derivatives['webp'][320]);
        Storage::disk('public')->assertExists($page->original_path);
    }

    public function test_pdf_upload_is_rasterized_page_by_page(): void
    {
        if ((new ExecutableFinder)->find(config('qdealme.pdftoppm_binary')) === null) {
            $this->markTestSkipped('pdftoppm (poppler-utils) is not installed.');
        }

        Storage::disk('local')->put('uploads/flyers/flyer.pdf', $this->pdf(3));
        $flyer = $this->draftFlyer(['uploads/flyers/flyer.pdf']);

        ProcessFlyerUpload::dispatchSync($flyer->id);

        $this->assertSame(3, $flyer->refresh()->page_count);
        $this->assertSame(FlyerStatus::InReview, $flyer->status);
    }

    public function test_reprocessing_is_idempotent(): void
    {
        Storage::disk('local')->put('uploads/flyers/a.jpg', $this->jpeg());
        $flyer = $this->draftFlyer(['uploads/flyers/a.jpg']);

        ProcessFlyerUpload::dispatchSync($flyer->id);
        ProcessFlyerUpload::dispatchSync($flyer->id);

        $this->assertSame(1, $flyer->refresh()->pages()->count());
    }

    public function test_failures_are_recorded_and_visible(): void
    {
        Storage::disk('local')->put('uploads/flyers/broken.pdf', 'not really a pdf');
        $flyer = $this->draftFlyer(['uploads/flyers/broken.pdf']);

        try {
            ProcessFlyerUpload::dispatchSync($flyer->id);
        } catch (\Throwable) {
            // The job rethrows so the queue can retry; the flyer records why.
        }

        $flyer->refresh();
        $this->assertSame(FlyerStatus::Failed, $flyer->status);
        $this->assertNotEmpty($flyer->processing_error);
    }
}
