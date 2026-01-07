<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Expense categories (Tailwind 400 - softer colors)
            ['name' => 'Food & Groceries', 'type' => 'expense', 'icon' => '🛒', 'color' => '#4ade80'],
            ['name' => 'Transport', 'type' => 'expense', 'icon' => '🚗', 'color' => '#60a5fa'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => '🏠', 'color' => '#a78bfa'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => '⚡', 'color' => '#fbbf24'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => '🏥', 'color' => '#f87171'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => '🎮', 'color' => '#f472b6'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => '🛍️', 'color' => '#2dd4bf'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => '🎓', 'color' => '#818cf8'],
            ['name' => 'Restaurants & Cafes', 'type' => 'expense', 'icon' => '🍽️', 'color' => '#fb923c'],
            ['name' => 'Subscriptions', 'type' => 'expense', 'icon' => '🔄', 'color' => '#c084fc'],
            ['name' => 'Personal Care', 'type' => 'expense', 'icon' => '✨', 'color' => '#e879f9'],
            ['name' => 'Gifts', 'type' => 'expense', 'icon' => '🎁', 'color' => '#fb7185'],
            ['name' => 'Travel', 'type' => 'expense', 'icon' => '✈️', 'color' => '#38bdf8'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'icon' => '📌', 'color' => '#94a3b8'],

            // Income categories (Tailwind 400 - softer colors)
            ['name' => 'Salary', 'type' => 'income', 'icon' => '💵', 'color' => '#4ade80'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻', 'color' => '#60a5fa'],
            ['name' => 'Investments', 'type' => 'income', 'icon' => '📈', 'color' => '#a78bfa'],
            ['name' => 'Gifts Received', 'type' => 'income', 'icon' => '🎀', 'color' => '#f472b6'],
            ['name' => 'Refunds', 'type' => 'income', 'icon' => '↩️', 'color' => '#2dd4bf'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => '💰', 'color' => '#94a3b8'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'type' => $category['type']],
                $category
            );
        }
    }
}
