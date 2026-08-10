<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name', 60)->default('Lomba Diskominfo');
            $table->string('logo_url', 500)->nullable();
            $table->string('primary_color', 7)->default('#0067A5');
            $table->string('primary_dark_color', 7)->default('#00476F');
            $table->string('accent_color', 7)->default('#E5B52A');
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            'brand_name' => 'Lomba Diskominfo',
            'logo_url' => '/images/logo-indramayu.png',
            'primary_color' => '#0067A5',
            'primary_dark_color' => '#00476F',
            'accent_color' => '#E5B52A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
