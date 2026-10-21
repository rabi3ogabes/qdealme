<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RetailerDirectorySeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Runs during each Vercel build (`composer run vercel`). Makes sure the built
 * assets exist (Laravel reads public/build/manifest.json at runtime), then brings the database
 * schema up to date and seeds the reference data the site needs: cities,
 * categories and, when ADMIN_PASSWORD is set, the first admin account.
 */
class VercelBuildCommand extends Command
{
    protected $signature = 'qdealme:vercel-build';

    protected $description = 'Prepare the database during a Vercel build';

    public function handle(): int
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->info('Built assets are missing; running the asset build.');

            $build = Process::forever()->path(base_path())->run('npm ci --no-audit --no-fund && npm run build', function (string $type, string $output): void {
                $this->output->write($output);
            });

            if ($build->failed()) {
                $this->error('The asset build failed.');

                return self::FAILURE;
            }
        }

        if (! $this->databaseConfigured()) {
            $this->warn('No database URL is set (DB_URL, POSTGRES_URL or DATABASE_URL). Skipping migrations; the site needs a database to work.');

            return self::SUCCESS;
        }

        try {
            $this->call('migrate', ['--force' => true]);
            $this->call('db:seed', ['--class' => LocationSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => CategorySeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => RetailerDirectorySeeder::class, '--force' => true]);

            if (filled(env('ADMIN_PASSWORD'))) {
                $this->call('db:seed', ['--class' => UserSeeder::class, '--force' => true]);
            } else {
                $this->warn('ADMIN_PASSWORD is not set, so no admin account was created. Set it in Vercel and redeploy.');
            }
        } catch (Throwable $e) {
            $this->error('Database preparation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Database is ready.');

        return self::SUCCESS;
    }

    private function databaseConfigured(): bool
    {
        return filled(config('database.connections.pgsql.url'))
            || ! in_array(config('database.connections.pgsql.host'), [null, '', '127.0.0.1', 'localhost'], true);
    }
}
