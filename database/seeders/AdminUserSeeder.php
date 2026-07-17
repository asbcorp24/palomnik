<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('palomnik.admin.email');
        $password = config('palomnik.admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('Администратор не создан: укажите ADMIN_EMAIL и ADMIN_PASSWORD в .env.');
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = config('palomnik.admin.name', 'Главный администратор');
        $user->role = User::ROLE_SUPER_ADMIN;
        $user->is_active = true;

        if (! $user->exists) {
            $user->password = Hash::make($password);
        }

        $user->save();
    }
}
