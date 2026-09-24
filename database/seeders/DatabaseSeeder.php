<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@soapbox.cloud'],
            [
                'name' => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('Admin@0147'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'tester@soapbox.cloud'],
            [
                'name' => 'Security Tester',
                'password' => \Illuminate\Support\Facades\Hash::make('Tester@0147'),
                'email_verified_at' => now(),
                'role' => 'tester',
            ]
        );
    }
}
