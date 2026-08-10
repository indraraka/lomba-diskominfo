<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', app()->isProduction() ? null : 'admin@lomba.test');
        $password = env('ADMIN_PASSWORD', app()->isProduction() ? null : 'Lomba17!');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('ADMIN_EMAIL dan ADMIN_PASSWORD wajib diisi untuk bootstrap production.');
        }

        User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin Lomba', 'password' => Hash::make($password)],
        );
    }
}
