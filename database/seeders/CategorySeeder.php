<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fantasy',
                'icon' => 'https://picsum.photos/seed/category-fantasy/128/128',
                'description' => 'Epic worlds, magic, myths, and adventures.',
            ],
            [
                'name' => 'Romance',
                'icon' => 'https://picsum.photos/seed/category-romance/128/128',
                'description' => 'Love stories, emotional journeys, and relationships.',
            ],
            [
                'name' => 'Science Fiction',
                'icon' => 'https://picsum.photos/seed/category-scifi/128/128',
                'description' => 'Future tech, space travel, and speculative ideas.',
            ],
            [
                'name' => 'Mystery',
                'icon' => 'https://picsum.photos/seed/category-mystery/128/128',
                'description' => 'Suspenseful investigations and hidden secrets.',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'icon' => $category['icon'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
