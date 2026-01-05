<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Expense categories
            ['name' => 'Food & Groceries', 'type' => 'expense', 'icon' => '🛒', 'color' => '#22c55e'],
            ['name' => 'Transport', 'type' => 'expense', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => '⚡', 'color' => '#f59e0b'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => '🏥', 'color' => '#ef4444'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => '🎮', 'color' => '#ec4899'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => '🛍️', 'color' => '#14b8a6'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => '🎓', 'color' => '#6366f1'],
            ['name' => 'Restaurants & Cafes', 'type' => 'expense', 'icon' => '🍽️', 'color' => '#f97316'],
            ['name' => 'Subscriptions', 'type' => 'expense', 'icon' => '🔄', 'color' => '#a855f7'],
            ['name' => 'Personal Care', 'type' => 'expense', 'icon' => '✨', 'color' => '#e879f9'],
            ['name' => 'Gifts', 'type' => 'expense', 'icon' => '🎁', 'color' => '#fb7185'],
            ['name' => 'Travel', 'type' => 'expense', 'icon' => '✈️', 'color' => '#0ea5e9'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'icon' => '📌', 'color' => '#64748b'],

            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'icon' => '💵', 'color' => '#22c55e'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻', 'color' => '#3b82f6'],
            ['name' => 'Investments', 'type' => 'income', 'icon' => '📈', 'color' => '#8b5cf6'],
            ['name' => 'Gifts Received', 'type' => 'income', 'icon' => '🎀', 'color' => '#ec4899'],
            ['name' => 'Refunds', 'type' => 'income', 'icon' => '↩️', 'color' => '#14b8a6'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => '💰', 'color' => '#64748b'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'type' => $category['type']],
                $category
            );
        }
    }
}
