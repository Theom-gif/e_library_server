<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class UserSeeder extends Seeder
{
    /**
     * Convert a local file path to a public URL by copying it to storage/app/public/avatars with an encrypted name.
     * Returns the URL to access the image via the server.
     */
    private function convertLocalImageToUrl($localPath)
    {
        if (!file_exists($localPath)) {
            return null;
        }
        $ext = pathinfo($localPath, PATHINFO_EXTENSION);
        $encryptedName = Str::random(40) . '.' . $ext;
        $storagePath = storage_path('app/public/avatars');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $destination = $storagePath . DIRECTORY_SEPARATOR . $encryptedName;
        copy($localPath, $destination);
        // Return the public URL
        return url('storage/avatars/' . $encryptedName);
    }
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
                'avatar'    => 'C:\Users\LOQ\Desktop\e_library_server\public\images\profile_cat.jpg',
                'role_id'   => 2,
            ],

            // Author
            [
                'firstname' => 'Author',
                'lastname'  => 'Writer',
                'email'     => 'author@example.com',
                'password'  => 'author123',
                'avatar'    => 'https://i.pinimg.com/736x/55/ef/e1/55efe105142bc9cc29dc123527af2e14.jpg',
                'role_id'   => 2,
            ],

            // User
            [
                'firstname' => 'Regular',
                'lastname'  => 'User',
                'email'     => 'user@example.com',
                'password'  => 'user123',
                'avatar'    => 'https://i.pinimg.com/736x/55/ef/e1/55efe105142bc9cc29dc123527af2e14.jpg',
                'role_id'   => 3,
            ],
        ];

        foreach ($users as $user) {
            // If avatar is a local file path, convert to URL
            if (isset($user['avatar']) && is_string($user['avatar']) && file_exists($user['avatar']) && !filter_var($user['avatar'], FILTER_VALIDATE_URL)) {
                $user['avatar'] = $this->convertLocalImageToUrl($user['avatar']);
            }
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'firstname' => $user['firstname'],
                    'lastname'  => $user['lastname'],
                    'password'  => Hash::make($user['password']),
                    'role_id'   => $user['role_id'],
                    'avatar'    => $user['avatar'] ?? null,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
