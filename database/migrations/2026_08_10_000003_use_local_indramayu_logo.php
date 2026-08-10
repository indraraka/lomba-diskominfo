<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $remoteLogo = 'https://diskominfo.indramayukab.go.id/asset/logo/logokabindramayu.png';

    public function up(): void
    {
        DB::table('app_settings')->where('logo_url', $this->remoteLogo)->update([
            'logo_url' => '/images/logo-indramayu.png',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('logo_url', '/images/logo-indramayu.png')->update([
            'logo_url' => $this->remoteLogo,
            'updated_at' => now(),
        ]);
    }
};
