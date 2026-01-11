<?php

namespace Database\Seeders;

use App\Enums\BudgetPeriod;
use App\Enums\DebtType;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Enums\TriggerType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\RecurringTransaction;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo user
        $user = User::updateOrCreate(
            ['email' => 'demo@demo.com'],
            [
                'name' => 'Demo User',
                'password' => 'demo',
                'role' => UserRole::ReadOnly,
            ]
        );

        $this->command->info('Demo user created: demo@demo.com / demo');

        // Get currencies
        $usd = Currency::where('code', 'USD')->first();
        $eur = Currency::where('code', 'EUR')->first();

        if (!$usd) {
            $this->command->error('Please run CurrencySeeder first');
            return;
        }

        // Create accounts
        $accounts = $this->createAccounts($usd, $eur);

        // Get categories
        $expenseCategories = Category::where('type', 'expense')->get();
        $incomeCategories = Category::where('type', 'income')->get();

        // Get tags
        $tags = Tag::all();

        // Create transactions for the last 6 months
        $this->createTransactions($accounts, $expenseCategories, $incomeCategories, $tags);

        // Create budgets
        $this->createBudgets($usd, $expenseCategories, $tags);

        // Create recurring transactions
        $this->createRecurringTransactions($accounts, $expenseCategories, $incomeCategories);

        // Create automation rules
        $this->createAutomationRules($tags);

        $this->command->info('Demo data seeded successfully!');
    }

    private function createAccounts(Currency $usd, ?Currency $eur): array
    {
        $accounts = [];

        // Main bank account
        $accounts['bank'] = Account::updateOrCreate(
            ['name' => 'Main Bank'],
            [
                'type' => 'bank',
                'currency_id' => $usd->id,
                'initial_balance' => 5000.00,
                'is_active' => true,
            ]
        );

        // Cash
        $accounts['cash'] = Account::updateOrCreate(
            ['name' => 'Cash Wallet'],
            [
                'type' => 'cash',
                'currency_id' => $usd->id,
                'initial_balance' => 500.00,
                'is_active' => true,
            ]
        );

        // Savings account
        $accounts['savings'] = Account::updateOrCreate(
            ['name' => 'Savings Account'],
            [
                'type' => 'bank',
                'currency_id' => $usd->id,
                'initial_balance' => 10000.00,
                'is_active' => true,
            ]
        );

        // Crypto
        $accounts['crypto'] = Account::updateOrCreate(
            ['name' => 'Crypto Portfolio'],
            [
                'type' => 'crypto',
                'currency_id' => $usd->id,
                'initial_balance' => 2500.00,
                'is_active' => true,
            ]
        );

        // EUR account if available
        if ($eur) {
            $accounts['eur'] = Account::updateOrCreate(
                ['name' => 'EUR Account'],
                [
                    'type' => 'bank',
                    'currency_id' => $eur->id,
                    'initial_balance' => 1000.00,
                    'is_active' => true,
                ]
            );
        }

        // Debt: I owe someone
        $accounts['debt_owe'] = Account::updateOrCreate(
            ['name' => 'Car Loan'],
            [
                'type' => 'debt',
                'currency_id' => $usd->id,
                'initial_balance' => 0,
                'is_active' => true,
                'debt_type' => DebtType::IOwe,
                'target_amount' => 15000.00,
                'due_date' => now()->addYears(2),
                'is_paid_off' => false,
                'counterparty' => 'Auto Finance Bank',
                'debt_description' => 'Car loan for Toyota Camry',
            ]
        );

        // Debt: Someone owes me
        $accounts['debt_owed'] = Account::updateOrCreate(
            ['name' => 'Loan to John'],
            [
                'type' => 'debt',
                'currency_id' => $usd->id,
                'initial_balance' => 0,
                'is_active' => true,
                'debt_type' => DebtType::OwedToMe,
                'target_amount' => 500.00,
                'due_date' => now()->addMonths(3),
                'is_paid_off' => false,
                'counterparty' => 'John Smith',
                'debt_description' => 'Personal loan',
            ]
        );

        $this->command->info('Created ' . count($accounts) . ' accounts');

        return $accounts;
    }

    private function createTransactions(array $accounts, $expenseCategories, $incomeCategories, $tags): void
    {
        $transactionCount = 0;
        $startDate = now()->subMonths(6);
        $endDate = now();

        // ===== MONTHLY INCOME =====
        $salaryCategory = $incomeCategories->firstWhere('name', 'Salary');
        $freelanceCategory = $incomeCategories->firstWhere('name', 'Freelance');
        $investmentsCategory = $incomeCategories->firstWhere('name', 'Investments');

        for ($i = 0; $i < 6; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);

            // Salary - 1st of month (for work done previous month)
            if ($salaryCategory) {
                $salaryDate = $monthDate->copy()->day(1);
                if ($salaryDate->lte($endDate) && $salaryDate->gte($startDate)) {
                    Transaction::create([
                        'type' => TransactionType::Income,
                        'account_id' => $accounts['bank']->id,
                        'category_id' => $salaryCategory->id,
                        'amount' => rand(4800, 5200),
                        'description' => 'Monthly salary',
                        'date' => $salaryDate,
                    ]);
                    $transactionCount++;
                }
            }

            // Freelance - random times per month (70% chance)
            if ($freelanceCategory && rand(1, 100) <= 70) {
                $freelanceDay = min(rand(5, 20), now()->day);
                $freelanceDate = $monthDate->copy()->day($freelanceDay);
                if ($freelanceDate->lte($endDate)) {
                    Transaction::create([
                        'type' => TransactionType::Income,
                        'account_id' => $accounts['bank']->id,
                        'category_id' => $freelanceCategory->id,
                        'amount' => rand(300, 800),
                        'description' => 'Freelance project',
                        'date' => $freelanceDate,
                    ]);
                    $transactionCount++;
                }
            }

            // Investments dividends - quarterly
            if ($investmentsCategory && $i % 3 === 0) {
                $investDate = $monthDate->copy()->day(min(15, now()->day));
                if ($investDate->lte($endDate)) {
                    Transaction::create([
                        'type' => TransactionType::Income,
                        'account_id' => $accounts['crypto']->id,
                        'category_id' => $investmentsCategory->id,
                        'amount' => rand(50, 150),
                        'description' => 'Investment dividends',
                        'date' => $investDate,
                    ]);
                    $transactionCount++;
                }
            }
        }

        // Current month salary (if we're past the 1st)
        if ($salaryCategory && now()->day >= 1) {
            Transaction::create([
                'type' => TransactionType::Income,
                'account_id' => $accounts['bank']->id,
                'category_id' => $salaryCategory->id,
                'amount' => rand(4800, 5200),
                'description' => 'Monthly salary',
                'date' => now()->startOfMonth(),
            ]);
            $transactionCount++;
        }

        // ===== MONTHLY FIXED EXPENSES =====
        $housingCategory = $expenseCategories->firstWhere('name', 'Housing');
        $utilitiesCategory = $expenseCategories->firstWhere('name', 'Utilities');
        $subscriptionsCategory = $expenseCategories->firstWhere('name', 'Subscriptions');

        for ($i = 0; $i < 6; $i++) {
            $monthDate = $startDate->copy()->addMonths($i);
            if ($monthDate->lte($endDate)) {
                // Rent - 1st of month
                if ($housingCategory) {
                    Transaction::create([
                        'type' => TransactionType::Expense,
                        'account_id' => $accounts['bank']->id,
                        'category_id' => $housingCategory->id,
                        'amount' => 1200,
                        'description' => 'Rent payment',
                        'date' => $monthDate->copy()->startOfMonth(),
                    ]);
                    $transactionCount++;
                }

                // Utilities - middle of month
                if ($utilitiesCategory) {
                    Transaction::create([
                        'type' => TransactionType::Expense,
                        'account_id' => $accounts['bank']->id,
                        'category_id' => $utilitiesCategory->id,
                        'amount' => rand(80, 150),
                        'description' => 'Electricity & Internet',
                        'date' => $monthDate->copy()->day(15),
                    ]);
                    $transactionCount++;
                }

                // Subscriptions
                if ($subscriptionsCategory) {
                    Transaction::create([
                        'type' => TransactionType::Expense,
                        'account_id' => $accounts['bank']->id,
                        'category_id' => $subscriptionsCategory->id,
                        'amount' => rand(30, 50),
                        'description' => 'Netflix, Spotify, etc.',
                        'date' => $monthDate->copy()->day(10),
                    ]);
                    $transactionCount++;
                }
            }
        }

        // ===== DAILY VARIABLE EXPENSES =====
        // Categories for daily random expenses (excluding Housing, Utilities, Subscriptions)
        $dailyCategories = $expenseCategories->filter(fn($c) => !in_array($c->name, [
            'Housing', 'Utilities', 'Subscriptions'
        ]));

        $expenseDescriptions = [
            'Food & Groceries' => ['Grocery shopping', 'Weekly groceries', 'Supermarket', 'Fresh produce'],
            'Transport' => ['Gas station', 'Uber ride', 'Metro pass', 'Parking'],
            'Restaurants & Cafes' => ['Lunch', 'Dinner out', 'Coffee shop', 'Fast food'],
            'Entertainment' => ['Movie tickets', 'Concert', 'Games', 'Bowling'],
            'Shopping' => ['Clothes', 'Electronics', 'Home stuff', 'Amazon'],
            'Healthcare' => ['Pharmacy', 'Doctor', 'Gym', 'Vitamins'],
            'Personal Care' => ['Haircut', 'Cosmetics', 'Spa'],
            'Gifts' => ['Birthday gift', 'Present'],
            'Travel' => ['Weekend trip', 'Hotel'],
            'Education' => ['Books', 'Course', 'Tutorial'],
            'Other Expenses' => ['Misc purchase'],
        ];

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            // 0-2 transactions per day (average ~1)
            $dailyTransactions = rand(0, 2);

            for ($i = 0; $i < $dailyTransactions; $i++) {
                $category = $dailyCategories->random();
                $descriptions = $expenseDescriptions[$category->name] ?? ['Purchase'];
                $description = $descriptions[array_rand($descriptions)];

                // Reasonable daily expense amounts
                $amount = match ($category->name) {
                    'Food & Groceries' => rand(15, 80),
                    'Transport' => rand(5, 40),
                    'Restaurants & Cafes' => rand(10, 45),
                    'Entertainment' => rand(10, 40),
                    'Shopping' => rand(15, 100),
                    'Healthcare' => rand(10, 50),
                    'Personal Care' => rand(15, 60),
                    'Gifts' => rand(20, 80),
                    'Travel' => rand(50, 200),
                    'Education' => rand(10, 50),
                    default => rand(10, 50),
                };

                $account = rand(0, 10) > 3 ? $accounts['bank'] : $accounts['cash'];

                $transaction = Transaction::create([
                    'type' => TransactionType::Expense,
                    'account_id' => $account->id,
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'description' => $description,
                    'date' => $currentDate->copy()->setTime(rand(8, 22), rand(0, 59)),
                ]);

                // Randomly attach tags (20% chance)
                if (rand(1, 100) <= 20 && $tags->isNotEmpty()) {
                    $transaction->tags()->attach($tags->random(rand(1, 2))->pluck('id'));
                }

                $transactionCount++;
            }

            $currentDate->addDay();
        }

        // ===== TRANSFERS =====
        for ($i = 0; $i < 6; $i++) {
            $date = $startDate->copy()->addMonths($i)->day(5);
            if ($date->lte($endDate)) {
                Transaction::create([
                    'type' => TransactionType::Transfer,
                    'account_id' => $accounts['bank']->id,
                    'to_account_id' => $accounts['savings']->id,
                    'amount' => rand(300, 500),
                    'to_amount' => rand(300, 500),
                    'description' => 'Monthly savings',
                    'date' => $date,
                ]);
                $transactionCount++;
            }
        }

        // ===== DEBT PAYMENTS =====
        for ($i = 0; $i < 6; $i++) {
            $date = $startDate->copy()->addMonths($i)->day(15);
            if ($date->lte($endDate)) {
                Transaction::create([
                    'type' => TransactionType::DebtPayment,
                    'account_id' => $accounts['bank']->id,
                    'to_account_id' => $accounts['debt_owe']->id,
                    'amount' => 400,
                    'to_amount' => 400,
                    'description' => 'Car loan payment',
                    'date' => $date,
                ]);
                $transactionCount++;
            }
        }

        // Debt collection (someone paid back)
        Transaction::create([
            'type' => TransactionType::DebtCollection,
            'account_id' => $accounts['bank']->id,
            'to_account_id' => $accounts['debt_owed']->id,
            'amount' => 150,
            'to_amount' => 150,
            'description' => 'John partial repayment',
            'date' => now()->subMonths(2),
        ]);
        $transactionCount++;

        $this->command->info("Created {$transactionCount} transactions");
    }

    private function createBudgets(Currency $usd, $expenseCategories, $tags): void
    {
        // Monthly food budget
        $foodCategory = $expenseCategories->firstWhere('name', 'Food & Groceries');
        if ($foodCategory) {
            $budget = Budget::updateOrCreate(
                ['name' => 'Monthly Food Budget'],
                [
                    'amount' => 600,
                    'currency_id' => $usd->id,
                    'period' => BudgetPeriod::Monthly,
                    'start_date' => now()->startOfMonth(),
                    'is_global' => false,
                    'notify_at_percent' => 80,
                    'is_active' => true,
                ]
            );
            $budget->categories()->sync([$foodCategory->id]);
        }

        // Entertainment budget
        $entertainmentCategory = $expenseCategories->firstWhere('name', 'Entertainment');
        if ($entertainmentCategory) {
            $budget = Budget::updateOrCreate(
                ['name' => 'Entertainment Budget'],
                [
                    'amount' => 200,
                    'currency_id' => $usd->id,
                    'period' => BudgetPeriod::Monthly,
                    'start_date' => now()->startOfMonth(),
                    'is_global' => false,
                    'notify_at_percent' => 90,
                    'is_active' => true,
                ]
            );
            $budget->categories()->sync([$entertainmentCategory->id]);
        }

        // Global monthly budget
        Budget::updateOrCreate(
            ['name' => 'Total Monthly Spending'],
            [
                'amount' => 3000,
                'currency_id' => $usd->id,
                'period' => BudgetPeriod::Monthly,
                'start_date' => now()->startOfMonth(),
                'is_global' => true,
                'notify_at_percent' => 85,
                'is_active' => true,
            ]
        );

        // Vacation budget (one-time)
        $vacationTag = $tags->firstWhere('name', 'Vacation');
        if ($vacationTag) {
            $budget = Budget::updateOrCreate(
                ['name' => 'Summer Vacation'],
                [
                    'amount' => 2000,
                    'currency_id' => $usd->id,
                    'period' => BudgetPeriod::OneTime,
                    'start_date' => now(),
                    'end_date' => now()->addMonths(6),
                    'is_global' => false,
                    'notify_at_percent' => 75,
                    'is_active' => true,
                ]
            );
            $budget->tags()->sync([$vacationTag->id]);
        }

        $this->command->info('Created budgets');
    }

    private function createRecurringTransactions(array $accounts, $expenseCategories, $incomeCategories): void
    {
        // Monthly salary
        $salaryCategory = $incomeCategories->firstWhere('name', 'Salary');
        if ($salaryCategory) {
            RecurringTransaction::updateOrCreate(
                ['description' => 'Monthly Salary'],
                [
                    'type' => TransactionType::Income,
                    'account_id' => $accounts['bank']->id,
                    'category_id' => $salaryCategory->id,
                    'amount' => 5000,
                    'frequency' => RecurringFrequency::Monthly,
                    'interval' => 1,
                    'day_of_month' => 28,
                    'start_date' => now()->startOfMonth(),
                    'next_run_date' => now()->endOfMonth(),
                    'is_active' => true,
                ]
            );
        }

        // Monthly rent
        $housingCategory = $expenseCategories->firstWhere('name', 'Housing');
        if ($housingCategory) {
            RecurringTransaction::updateOrCreate(
                ['description' => 'Rent Payment'],
                [
                    'type' => TransactionType::Expense,
                    'account_id' => $accounts['bank']->id,
                    'category_id' => $housingCategory->id,
                    'amount' => 1200,
                    'frequency' => RecurringFrequency::Monthly,
                    'interval' => 1,
                    'day_of_month' => 1,
                    'start_date' => now()->startOfMonth(),
                    'next_run_date' => now()->addMonth()->startOfMonth(),
                    'is_active' => true,
                ]
            );
        }

        // Weekly groceries
        $foodCategory = $expenseCategories->firstWhere('name', 'Food & Groceries');
        if ($foodCategory) {
            RecurringTransaction::updateOrCreate(
                ['description' => 'Weekly Groceries'],
                [
                    'type' => TransactionType::Expense,
                    'account_id' => $accounts['bank']->id,
                    'category_id' => $foodCategory->id,
                    'amount' => 100,
                    'frequency' => RecurringFrequency::Weekly,
                    'interval' => 1,
                    'day_of_week' => 6, // Saturday
                    'start_date' => now()->startOfWeek(),
                    'next_run_date' => now()->next('Saturday'),
                    'is_active' => true,
                ]
            );
        }

        // Monthly subscriptions
        $subscriptionsCategory = $expenseCategories->firstWhere('name', 'Subscriptions');
        if ($subscriptionsCategory) {
            RecurringTransaction::updateOrCreate(
                ['description' => 'Netflix Subscription'],
                [
                    'type' => TransactionType::Expense,
                    'account_id' => $accounts['bank']->id,
                    'category_id' => $subscriptionsCategory->id,
                    'amount' => 15.99,
                    'frequency' => RecurringFrequency::Monthly,
                    'interval' => 1,
                    'day_of_month' => 10,
                    'start_date' => now()->startOfMonth(),
                    'next_run_date' => now()->day(10)->addMonthNoOverflow(),
                    'is_active' => true,
                ]
            );
        }

        // Monthly savings transfer
        RecurringTransaction::updateOrCreate(
            ['description' => 'Monthly Savings'],
            [
                'type' => TransactionType::Transfer,
                'account_id' => $accounts['bank']->id,
                'to_account_id' => $accounts['savings']->id,
                'amount' => 500,
                'to_amount' => 500,
                'frequency' => RecurringFrequency::Monthly,
                'interval' => 1,
                'day_of_month' => 5,
                'start_date' => now()->startOfMonth(),
                'next_run_date' => now()->day(5)->addMonthNoOverflow(),
                'is_active' => true,
            ]
        );

        $this->command->info('Created recurring transactions');
    }

    private function createAutomationRules($tags): void
    {
        $essentialTag = $tags->firstWhere('name', 'Essential');
        $recurringTag = $tags->firstWhere('name', 'Recurring');

        // Auto-tag large transactions
        AutomationRule::updateOrCreate(
            ['name' => 'Tag large transactions'],
            [
                'description' => 'Automatically tag transactions over $500 as Essential',
                'trigger_type' => TriggerType::OnTransactionCreate,
                'priority' => 1,
                'conditions' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'amount', 'op' => 'gt', 'value' => 500],
                    ],
                ],
                'actions' => $essentialTag ? [
                    ['type' => 'add_tags', 'tag_ids' => [$essentialTag->id]],
                ] : [],
                'is_active' => true,
                'stop_processing' => false,
            ]
        );

        // Auto-tag recurring description
        if ($recurringTag) {
            AutomationRule::updateOrCreate(
                ['name' => 'Tag subscription transactions'],
                [
                    'description' => 'Tag transactions with "subscription" in description',
                    'trigger_type' => TriggerType::OnTransactionCreate,
                    'priority' => 2,
                    'conditions' => [
                        'match' => 'all',
                        'conditions' => [
                            ['field' => 'description', 'op' => 'contains', 'value' => 'subscription'],
                        ],
                    ],
                    'actions' => [
                        ['type' => 'add_tags', 'tag_ids' => [$recurringTag->id]],
                    ],
                    'is_active' => true,
                    'stop_processing' => false,
                ]
            );
        }

        $this->command->info('Created automation rules');
    }
}
