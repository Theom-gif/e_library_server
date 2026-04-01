<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Admin', 'description' => 'Administrator with full access'],
            ['id' => 2, 'name' => 'Author', 'description' => 'Author with limited access'],
            ['id' => 3, 'name' => 'User', 'description' => 'Regular user with basic access'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('id', [1, 2, 3])->delete();
    }
};
