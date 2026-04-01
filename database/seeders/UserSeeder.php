<?php

namespace Database\Seeders;

use App\Support\PublicImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private function normalizeAvatar(?string $value): ?string
    {
        return PublicImage::normalize($value, 'avatars')['url'] ?? null;
    }

    public function run(): void
    {
        $users = [

            // Admins
            [
                'firstname' => 'Admin1',
                'lastname'  => 'System',
                'email'     => 'phornya26@gmail.com',
                'status'    => 'active',
                'password'  => 'yadmin123',
                'avatar'    => 'https://picsum.photos/seed/admin-1/300/300',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin2',
                'lastname'  => 'System',
                'email'     => 'sokthalyta@gmail.com',
                'status'    => 'active',
                'password'  => 'yadmin123',
                'avatar'    => 'C:\Users\Admin\Desktop\VC1-Backend\public\storage\books\covers\cute.jpg',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin3',
                'lastname'  => 'System',
                'email'     => 'sinatlek026@gmail.com',
                'status'    => 'active',
                'password'  => 'yadmin123',
                'avatar'    => 'C:\Users\Admin\Desktop\VC1-Backend\public\storage\books\covers\Wh67mzj2fSQeEm6phEftDH18aUZ6CGu5lZzUzV1L.png',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin4',
                'lastname'  => 'System',
                'email'     => 'hengliheang91@gmail.com',
                'status'    => 'active',
                'password'  => 'yadmin123',
                'avatar'    => 'C:\Users\Admin\Desktop\VC1-Backend\public\storage\books\covers\p6p7QLwRuHqfT2F3ewUbf4TCWA1vMANVAObxMKD6.jpg',
                'role_id'   => 1,
            ],
            [
                'firstname' => 'Admin5',
                'lastname'  => 'System',
                'email'     => 'seylasok311@gmail.com',
                'status'    => 'in_review',
                'password'  => 'yadmin123',
                'avatar'    => 'C:\Users\Admin\Desktop\VC1-Backend\public\storage\books\covers\cKnlAkQEGpXw05eWtzQnkLgIXCPHq2DBEYyP8Bwy.jpg',
                'role_id'   => 2,
            ],

            // Author
            [
                'firstname' => 'Author',
                'lastname'  => 'Writer',
                'email'     => 'author@example.com',
                'status'    => 'in_review',
                'password'  => 'author123',
                'avatar'    => 'C:\Users\Admin\Desktop\VC1-Backend\public\storage\books\covers\1U6dvCPuLOdUUFlnFVJqL0igH3sjAmVTTr3qyBVT.jpg',
                'role_id'   => 2,
            ],

            // User
            [
                'firstname' => 'Regular',
                'lastname'  => 'User',
                'email'     => 'user@example.com',
                'status'    => 'active',
                'password'  => 'user123',
                'avatar'    => 'https://picsum.photos/seed/regular-user/300/300',
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
                    'status'    => $user['status'] ?? 'active',
                    'role_id'   => $user['role_id'],
                    'avatar'    => $this->normalizeAvatar($user['avatar'] ?? null),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
