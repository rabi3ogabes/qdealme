<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@qdealme.com');
        $password = (string) env('ADMIN_PASSWORD', '');
        $generated = false;

        if ($password === '') {
            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin === null) {
            User::query()->create([
                'name' => 'QDealMe Admin',
                'email' => $email,
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'locale' => 'ar',
                'email_verified_at' => now(),
            ]);

            $this->command?->info("Super admin: {$email}");

            if ($generated) {
                $this->command?->warn("Generated password: {$password}  (set ADMIN_PASSWORD in .env to choose your own)");
            }
        }
    }
}
