<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'code' => 'streak_3',
                'title' => 'Streak Starter',
                'description' => 'Read on 3 consecutive days.',
                'icon' => 'fire',
                'condition_type' => 'STREAK',
                'condition_value' => 3,
            ],
            [
                'code' => 'elite_10',
                'title' => 'Elite Reader',
                'description' => 'Read 10 different books.',
                'icon' => 'trophy',
                'condition_type' => 'COUNT',
                'condition_value' => 10,
            ],
            [
                'code' => 'fast_60',
                'title' => 'Fast Reader',
                'description' => 'Finish a book within 60 minutes of total reading time.',
                'icon' => 'bolt',
                'condition_type' => 'SPEED',
                'condition_value' => 60,
            ],
            [
                'code' => 'scholar_3',
                'title' => 'Scholar',
                'description' => 'Read books from 3 different categories.',
                'icon' => 'book-open',
                'condition_type' => 'CATEGORY',
                'condition_value' => 3,
            ],
            [
                'code' => 'critic_5',
                'title' => 'Critic',
                'description' => 'Write 5 reviews.',
                'icon' => 'star',
                'condition_type' => 'REVIEW',
                'condition_value' => 5,
            ],
        ];

        foreach ($achievements as $achievement) {
            DB::table('achievements')->updateOrInsert(
                ['code' => $achievement['code']],
                [
                    'title' => $achievement['title'],
                    'description' => $achievement['description'],
                    'icon' => $achievement['icon'],
                    'condition_type' => $achievement['condition_type'],
                    'condition_value' => $achievement['condition_value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
