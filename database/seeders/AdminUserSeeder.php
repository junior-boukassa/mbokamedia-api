<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->shouldSeed()) {
            return;
        }

        $email = (string) env('DEFAULT_ADMIN_EMAIL', 'local-admin@example.test');
        $password = (string) env('DEFAULT_ADMIN_PASSWORD', 'ChangeMe123!');

        if ($this->usesUnsafeProductionDefaults($email, $password)) {
            throw new RuntimeException('Refusing to seed the default admin in production with placeholder credentials. Set secure DEFAULT_ADMIN_* values first.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('DEFAULT_ADMIN_NAME', 'Local Admin'),
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['super_admin']);
    }

    private function shouldSeed(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return (bool) env('SEED_DEFAULT_ADMIN', true);
        }

        return (bool) env('SEED_DEFAULT_ADMIN', false);
    }

    private function usesUnsafeProductionDefaults(string $email, string $password): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return $email === 'local-admin@example.test'
            || $password === 'ChangeMe123!';
    }
}
