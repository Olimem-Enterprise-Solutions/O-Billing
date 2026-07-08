<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the initial admin login. The Plumtree municipality (tenant) is created
 * and attached to every user by the `sage:import` command, so this seeder only
 * needs to ensure a user exists to log in with.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'alistairholmes95@gmail.com'],
            ['name' => 'Administrator', 'password' => Hash::make('Olimem@2026')],
        );
    }
}
