<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Сидер для тестирования Reports API.
 * Создаёт транзакции с известными суммами для проверки расчётов.
 *
 * Ожидаемые результаты за январь 2026:
 * - Income: 5000 USD + 1000 EUR * 0.855 = 5000 + 855 = 5855 USD
 * - Expenses: 1500 + 800 + 300 + 200 + 100 = 2900 USD
 * - Net Cash Flow: 5855 - 2900 = 2955 USD
 * - Savings Rate: (2955 / 5855) * 100 = 50.47%
 */
class ReportsTestSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем валюты
        $usd = Currency::where('code', 'USD')->first();
        $eur = Currency::where('code', 'EUR')->first();

        if (!$usd || !$eur) {
            $this->command->error('Currencies USD and EUR must exist!');
            return;
        }

        // Создаём аккаунты
        $mainAccount = Account::firstOrCreate(
            ['name' => 'Main Checking'],
            [
                'type' => 'bank',
                'currency_id' => $usd->id,
                'initial_balance' => 10000,
                'is_active' => true,
            ]
        );

        $euroAccount = Account::firstOrCreate(
            ['name' => 'Euro Savings'],
            [
                'type' => 'bank',
                'currency_id' => $eur->id,
                'initial_balance' => 5000,
                'is_active' => true,
            ]
        );

        // Создаём категории доходов
        $salaryCategory = Category::firstOrCreate(
            ['name' => 'Salary', 'type' => 'income'],
            ['icon' => 'briefcase', 'color' => '#22c55e']
        );

        $freelanceCategory = Category::firstOrCreate(
            ['name' => 'Freelance', 'type' => 'income'],
            ['icon' => 'laptop', 'color' => '#16a34a']
        );

        // Получаем категории расходов
        $foodCategory = Category::where('name', 'Food')->where('type', 'expense')->first()
            ?? Category::create(['name' => 'Food', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#ef4444']);

        $transportCategory = Category::where('name', 'Transport')->where('type', 'expense')->first()
            ?? Category::create(['name' => 'Transport', 'type' => 'expense', 'icon' => 'car', 'color' => '#f97316']);

        $entertainmentCategory = Category::where('name', 'Entertainment')->where('type', 'expense')->first()
            ?? Category::create(['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'gamepad', 'color' => '#8b5cf6']);

        $utilitiesCategory = Category::where('name', 'Utilities')->where('type', 'expense')->first()
            ?? Category::create(['name' => 'Utilities', 'type' => 'expense', 'icon' => 'zap', 'color' => '#eab308']);

        $shoppingCategory = Category::where('name', 'Shopping')->where('type', 'expense')->first()
            ?? Category::create(['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#ec4899']);

        // Удаляем старые тестовые транзакции (по описанию)
        Transaction::where('description', 'like', '[TEST]%')->delete();

        // ЯНВАРЬ 2026 - Транзакции с известными суммами

        // ДОХОДЫ (Total: 5855 USD в базовой валюте)
        // 5000 USD зарплата
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $salaryCategory->id,
            'type' => 'income',
            'amount' => 5000.00,
            'description' => '[TEST] January Salary',
            'date' => '2026-01-05',
        ]);

        // 1000 EUR фриланс (= 855 USD при rate 0.855)
        Transaction::create([
            'account_id' => $euroAccount->id,
            'category_id' => $freelanceCategory->id,
            'type' => 'income',
            'amount' => 1000.00,
            'description' => '[TEST] Freelance Project',
            'date' => '2026-01-15',
        ]);

        // РАСХОДЫ (Total: 2900 USD)
        // Food: 1500 USD
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $foodCategory->id,
            'type' => 'expense',
            'amount' => 500.00,
            'description' => '[TEST] Groceries week 1',
            'date' => '2026-01-07',
        ]);
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $foodCategory->id,
            'type' => 'expense',
            'amount' => 500.00,
            'description' => '[TEST] Groceries week 2',
            'date' => '2026-01-14',
        ]);
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $foodCategory->id,
            'type' => 'expense',
            'amount' => 500.00,
            'description' => '[TEST] Groceries week 3',
            'date' => '2026-01-21',
        ]);

        // Transport: 800 USD
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $transportCategory->id,
            'type' => 'expense',
            'amount' => 400.00,
            'description' => '[TEST] Gas',
            'date' => '2026-01-10',
        ]);
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $transportCategory->id,
            'type' => 'expense',
            'amount' => 400.00,
            'description' => '[TEST] Car maintenance',
            'date' => '2026-01-20',
        ]);

        // Entertainment: 300 USD
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $entertainmentCategory->id,
            'type' => 'expense',
            'amount' => 300.00,
            'description' => '[TEST] Concert tickets',
            'date' => '2026-01-18',
        ]);

        // Utilities: 200 USD
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $utilitiesCategory->id,
            'type' => 'expense',
            'amount' => 200.00,
            'description' => '[TEST] Electricity bill',
            'date' => '2026-01-25',
        ]);

        // Shopping: 100 USD
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $shoppingCategory->id,
            'type' => 'expense',
            'amount' => 100.00,
            'description' => '[TEST] New headphones',
            'date' => '2026-01-28',
        ]);

        // ДЕКАБРЬ 2025 - для сравнения периодов
        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $salaryCategory->id,
            'type' => 'income',
            'amount' => 4500.00,
            'description' => '[TEST] December Salary',
            'date' => '2025-12-05',
        ]);

        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $foodCategory->id,
            'type' => 'expense',
            'amount' => 1200.00,
            'description' => '[TEST] December Food',
            'date' => '2025-12-15',
        ]);

        Transaction::create([
            'account_id' => $mainAccount->id,
            'category_id' => $transportCategory->id,
            'type' => 'expense',
            'amount' => 600.00,
            'description' => '[TEST] December Transport',
            'date' => '2025-12-20',
        ]);

        // Трансфер для проверки Net Worth
        Transaction::create([
            'account_id' => $mainAccount->id,
            'to_account_id' => $euroAccount->id,
            'type' => 'transfer',
            'amount' => 1000.00,
            'to_amount' => 855.00, // 1000 USD -> 855 EUR
            'description' => '[TEST] Transfer to Euro account',
            'date' => '2026-01-30',
        ]);

        $this->command->info('Test data created successfully!');
        $this->command->info('');
        $this->command->info('Expected January 2026 results:');
        $this->command->info('  Income: 5000 USD + 1000 EUR * 0.855 = 5855 USD');
        $this->command->info('  Expenses: 1500 + 800 + 300 + 200 + 100 = 2900 USD');
        $this->command->info('  Net Cash Flow: 5855 - 2900 = 2955 USD');
        $this->command->info('  Savings Rate: (2955 / 5855) * 100 = 50.47%');
        $this->command->info('');
        $this->command->info('Expected December 2025 results:');
        $this->command->info('  Income: 4500 USD');
        $this->command->info('  Expenses: 1200 + 600 = 1800 USD');
        $this->command->info('  Net Cash Flow: 4500 - 1800 = 2700 USD');
    }
}
