<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_open_branding_settings(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('settings.edit'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('settings.brand_name', 'Lomba Diskominfo')
            ->where('settings.primary_color', '#0067A5'));
    }

    public function test_authenticated_admin_can_update_branding_settings(): void
    {
        $response = $this->actingAs(User::factory()->create())->patch(route('settings.update'), [
            'brand_name' => 'Lomba Kominfo Indramayu',
            'logo_url' => 'https://example.com/logo.png',
            'primary_color' => '#123456',
            'primary_dark_color' => '#102030',
            'accent_color' => '#FEDCBA',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $this->assertDatabaseHas('app_settings', [
            'brand_name' => 'Lomba Kominfo Indramayu',
            'primary_color' => '#123456',
        ]);
    }

    public function test_branding_update_rejects_invalid_colors(): void
    {
        $response = $this->actingAs(User::factory()->create())->from(route('settings.edit'))->patch(route('settings.update'), [
            ...AppSetting::defaults(),
            'primary_color' => 'blue',
        ]);

        $response->assertRedirect(route('settings.edit'))->assertSessionHasErrors('primary_color');
    }

    public function test_authenticated_admin_can_upload_a_brand_logo(): void
    {
        Storage::fake('branding');

        $response = $this->actingAs(User::factory()->create())->post(route('settings.update'), [
            ...AppSetting::defaults(),
            'logo' => UploadedFile::fake()->image('logo-baru.png', 500, 500),
        ]);

        $response->assertRedirect(route('settings.edit'));
        $logoPath = str(AppSetting::query()->first()->logo_url)->after('/uploads/branding/')->toString();
        Storage::disk('branding')->assertExists($logoPath);
    }
}
