<?php

namespace Database\Seeders;

use App\Models\MobileApp;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('admin.password');
        if (!$password) {
            throw new \RuntimeException('Set ADMIN_PASSWORD in .env before seeding.');
        }

        User::updateOrCreate(
            ['email' => config('admin.email')],
            ['name' => config('admin.name'), 'password' => Hash::make($password)],
        );

        MobileApp::firstOrCreate(
            ['package_name' => 'com.ngd.demo'],
            ['app_name' => 'Demo App', 'status' => true],
        );

        // Creates the API bearer token if it does not exist yet.
        Setting::apiToken();
    }
}
