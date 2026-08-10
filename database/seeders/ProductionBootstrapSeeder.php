<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists() || Competition::query()->exists() || CompetitionSession::query()->exists()) {
            throw new RuntimeException('Bootstrap dibatalkan: database aplikasi tidak kosong. Seeder production hanya boleh dijalankan sekali.');
        }

        $this->call([
            AdminSeeder::class,
            LombaAgustusanSeeder::class,
            PesertaLombaPerBidangSeeder::class,
        ]);
    }
}
