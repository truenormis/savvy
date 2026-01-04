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
            ['name' => 'Food & Groceries', 'type' => 'expense', 'icon' => 'shopping-cart', 'color' => '#22c55e'],
            ['name' => 'Transport', 'type' => 'expense', 'icon' => 'car', 'color' => '#3b82f6'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#8b5cf6'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'zap', 'color' => '#f59e0b'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => 'heart-pulse', 'color' => '#ef4444'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'gamepad-2', 'color' => '#ec4899'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#14b8a6'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'graduation-cap', 'color' => '#6366f1'],
            ['name' => 'Restaurants & Cafes', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#f97316'],
            ['name' => 'Subscriptions', 'type' => 'expense', 'icon' => 'repeat', 'color' => '#a855f7'],
            ['name' => 'Personal Care', 'type' => 'expense', 'icon' => 'sparkles', 'color' => '#e879f9'],
            ['name' => 'Gifts', 'type' => 'expense', 'icon' => 'gift', 'color' => '#fb7185'],
            ['name' => 'Travel', 'type' => 'expense', 'icon' => 'plane', 'color' => '#0ea5e9'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'icon' => 'circle-dot', 'color' => '#64748b'],

            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'banknote', 'color' => '#22c55e'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => 'laptop', 'color' => '#3b82f6'],
            ['name' => 'Investments', 'type' => 'income', 'icon' => 'trending-up', 'color' => '#8b5cf6'],
            ['name' => 'Gifts Received', 'type' => 'income', 'icon' => 'gift', 'color' => '#ec4899'],
            ['name' => 'Refunds', 'type' => 'income', 'icon' => 'undo', 'color' => '#14b8a6'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'circle-dot', 'color' => '#64748b'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'type' => $category['type']],
                $category
            );
        }
    }
}
