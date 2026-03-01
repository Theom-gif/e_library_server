<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'role_name' => 'Admin',
                'description' => 'System Administrator',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_name' => 'Author',
                'description' => 'Create book for reader',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_name' => 'User',
                'description' => 'Normal User',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
