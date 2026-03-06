<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {   $this->call(UserSeeder::class);
        DB::table('users')->insert([
            [
                'firstname' => 'Admin',
                'lastname' => 'Account',
                'email' => 'admin@example.com',
                'password' => Hash::make('12345678'),
                'bio' => 'Main administrator account',
                'facebook_url' => null,
                'avatar' => null,
                'role_id' => 1, // Admin role
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'firstname' => 'Author',
                'lastname' => 'Account',
                'email' => 'author@example.com',
                'password' => Hash::make('12345678'),
                'bio' => 'Main administrator account',
                'facebook_url' => null,
                'avatar' => null,
                'role_id' => 2, // Author role
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'firstname' => 'Normal',
                'lastname' => 'User',
                'email' => 'user@example.com',
                'password' => Hash::make('12345678'),
                'bio' => 'Regular user account',
                'facebook_url' => null,
                'avatar' => null,
                'role_id' => 3, // User role
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}