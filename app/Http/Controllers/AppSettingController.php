<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AppSettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Edit', ['settings' => AppSetting::branding()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:60'],
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)/i'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primary_dark_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $filename = 'logo-'.Str::uuid().'.'.$logo->extension();
            $logo->storeAs('', $filename, 'branding');
            $data['logo_url'] = '/uploads/branding/'.$filename;
        }
        unset($data['logo']);

        AppSetting::query()->firstOrCreate([], AppSetting::defaults())->update($data);

        return Redirect::route('settings.edit')->with('success', 'Identitas aplikasi berhasil diperbarui.');
    }
}
