<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [

            // Admins
            [
                'firstname' => 'Admin1',
                'lastname'  => 'System',
                'email'     => 'phornya26@gmail.com',
                'password'  => 'yadmin123',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin2',
                'lastname'  => 'System',
                'email'     => 'sokthalyta@gmail.com',
                'password'  => 'yadmin123',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin3',
                'lastname'  => 'System',
                'email'     => 'sinatlek026@gmail.com',
                'password'  => 'yadmin123',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin4',
                'lastname'  => 'System',
                'email'     => 'hengliheang91@gmail.com',
                'password'  => 'yadmin123',
                'role_id'   => 3,
            ],
            [
                'firstname' => 'Admin5',
                'lastname'  => 'System',
                'email'     => 'seylasok311@gmail.com',
                'password'  => 'yadmin123',
                'role_id'   => 2,
            ],

            // Author
            [
                'firstname' => 'Author',
                'lastname'  => 'Writer',
                'email'     => 'author@example.com',
                'password'  => 'author123',
                'role_id'   => 2,
            ],

            // User
            [
                'firstname' => 'Regular',
                'lastname'  => 'User',
                'email'     => 'user@example.com',
                'password'  => 'user123',
                'role_id'   => 3,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'firstname' => $user['firstname'],
                    'lastname'  => $user['lastname'],
                    'password'  => Hash::make($user['password']),
                    'role_id'   => $user['role_id'],
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
