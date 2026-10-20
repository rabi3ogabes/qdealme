<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerlessSupportTest extends TestCase
{
    private function png(int $w = 700, int $h = 1000): string
    {
        $im = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function draft(array $files): Flyer
    {
        $flyer = $this->makeFlyer(['status' => FlyerStatus::Draft], null, 0);
        $flyer->forceFill(['upload_files' => $files])->save();

        return $flyer;
    }

    /**
     * Run a callback with environment variables overridden everywhere env()
     * looks ($_SERVER, $_ENV and getenv), so values loaded from .env cannot
     * shadow them, then restore the previous values.
     */
    private function withEnvironment(array $vars, callable $callback): mixed
    {
        $previous = [];

        foreach ($vars as $name => $value) {
            $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            $this->setEnvironmentValue($name, $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $name => [$server, $env, $process]) {
                $this->setEnvironmentValue($name, $process === false ? null : $process);
                unset($_SERVER[$name], $_ENV[$name]);

                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }

                if ($env !== null) {
                    $_ENV[$name] = $env;
                }
            }
        }
    }

    private function setEnvironmentValue(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_SERVER[$name], $_ENV[$name]);

            return;
        }

        putenv("{$name}={$value}");
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
    }

    public function test_basic_mode_stores_uploaded_images_without_resizing(): void
    {
        config(['qdealme.image_processing' => 'basic']);
        Storage::disk('local')->put('uploads/flyers/p1.png', $this->png());
        $flyer = $this->draft(['uploads/flyers/p1.png']);

        ProcessFlyerUpload::dispatchSync($flyer->id);

        $flyer->refresh();
        $page = $flyer->pages()->first();

        $this->assertSame(FlyerStatus::InReview, $flyer->status);
        $this->assertSame(700, $page->width);
        $this->assertSame(1000, $page->height);
        $this->assertSame([], $page->derivatives);
        $this->assertStringEndsWith('original.png', $page->original_path);
        Storage::disk('public')->assertExists($page->original_path);
        $this->assertStringEndsWith('original.png', $page->url(640));
    }

    public function test_basic_mode_refuses_pdfs_with_a_clear_message(): void
    {
        config(['qdealme.image_processing' => 'basic']);
        Storage::disk('local')->put('uploads/flyers/f.pdf', "%PDF-1.4\n%%EOF");
        $flyer = $this->draft(['uploads/flyers/f.pdf']);

        try {
            ProcessFlyerUpload::dispatchSync($flyer->id);
        } catch (\Throwable) {
            // Recorded on the flyer below.
        }

        $flyer->refresh();
        $this->assertSame(FlyerStatus::Failed, $flyer->status);
        $this->assertStringContainsString('Upload the pages as JPG, PNG or WebP', $flyer->processing_error);
    }

    public function test_basic_mode_rejects_files_that_are_not_images(): void
    {
        config(['qdealme.image_processing' => 'basic']);
        Storage::disk('local')->put('uploads/flyers/x.png', 'not an image');
        $flyer = $this->draft(['uploads/flyers/x.png']);

        try {
            ProcessFlyerUpload::dispatchSync($flyer->id);
        } catch (\Throwable) {
        }

        $this->assertSame(FlyerStatus::Failed, $flyer->refresh()->status);
    }

    public function test_cron_endpoint_is_disabled_without_a_secret(): void
    {
        config(['qdealme.cron_secret' => '']);

        $this->get('/cron/flyers/roll')->assertNotFound();
    }

    public function test_cron_endpoint_requires_the_secret_and_rolls_flyers(): void
    {
        config(['qdealme.cron_secret' => 'top-secret']);
        $ended = $this->makeFlyer(['valid_from' => today()->subDays(9), 'valid_until' => today()->subDays(2)]);

        $this->get('/cron/flyers/roll')->assertUnauthorized();
        $this->get('/cron/flyers/roll', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $this->get('/cron/flyers/roll', ['Authorization' => 'Bearer top-secret'])
            ->assertOk()
            ->assertJson(['expired' => 1]);

        $this->assertSame(FlyerStatus::Expired, $ended->refresh()->status);
    }

    public function test_database_url_falls_back_to_vercel_integration_variables(): void
    {
        $config = fn () => require config_path('database.php');

        $url = $this->withEnvironment(
            ['DB_URL' => null, 'POSTGRES_URL' => 'postgresql://u:p@db.example.test:5432/app'],
            fn () => $config()['connections']['pgsql']['url'],
        );

        $this->assertSame('postgresql://u:p@db.example.test:5432/app', $url);
    }

    public function test_cloud_media_storage_switches_both_disks_to_s3(): void
    {
        $config = fn () => require config_path('filesystems.php');

        $disks = $this->withEnvironment(['MEDIA_STORAGE' => 'cloud'], fn () => $config()['disks']);
        $this->assertSame('s3', $disks['public']['driver']);
        $this->assertSame('s3', $disks['local']['driver']);
        $this->assertSame('private', $disks['local']['visibility']);

        $disks = $this->withEnvironment(['MEDIA_STORAGE' => 'disk'], fn () => $config()['disks']);
        $this->assertSame('local', $disks['public']['driver']);
    }

    public function test_vercel_never_serves_php_or_dotfiles_as_static_downloads(): void
    {
        $routes = json_decode((string) file_get_contents(base_path('vercel.json')), true)['routes'];
        $filesystem = array_search(['handle' => 'filesystem'], $routes, true);
        $this->assertIsInt($filesystem);

        $beforeFilesystem = array_slice($routes, 0, $filesystem);
        $routedToApp = fn (string $path) => collect($beforeFilesystem)->contains(
            fn (array $route) => preg_match('#^'.$route['src'].'$#', $path) === 1 && $route['dest'] === '/api/index.php'
        );

        // public/index.php would otherwise be sent to the browser as a raw file.
        foreach (['/', '/index.php', '/index.php/anything', '/.htaccess', '/.env'] as $path) {
            $this->assertTrue($routedToApp($path), "{$path} must be handled by the app, not served as a file");
        }

        foreach (['/robots.txt', '/build/manifest.json', '/css/qdealme-admin.css'] as $path) {
            $this->assertFalse($routedToApp($path), "{$path} should still be served as a static file");
        }
    }

    public function test_vercel_build_skips_cleanly_without_a_database_url(): void
    {
        config(['database.connections.pgsql.url' => null, 'database.connections.pgsql.host' => '127.0.0.1']);

        $this->artisan('qdealme:vercel-build')
            ->expectsOutputToContain('Skipping migrations')
            ->assertSuccessful();
    }
}
