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
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 12;

    private array $merchants = [
        'Food & Groceries' => ['Whole Foods Market', 'Trader Joe\'s', 'Costco Wholesale', 'Walmart', 'Kroger', 'Aldi', 'Target', 'Safeway'],
        'Transport' => ['Shell', 'BP', 'Chevron', 'Uber', 'Lyft', 'Metro Transit', 'ParkMobile', 'Jiffy Lube'],
        'Restaurants & Cafes' => ['Starbucks', 'Chipotle Mexican Grill', 'McDonald\'s', 'Olive Garden', 'Subway', 'Domino\'s Pizza', 'Panera Bread', 'Five Guys'],
        'Entertainment' => ['AMC Theatres', 'Steam', 'PlayStation Store', 'Ticketmaster', 'Dave & Buster\'s', 'Topgolf'],
        'Shopping' => ['Amazon', 'Best Buy', 'IKEA', 'Nike', 'Apple Store', 'Zara', 'H&M', 'Home Depot'],
        'Healthcare' => ['CVS Pharmacy', 'Walgreens', 'City Medical Clinic', 'Planet Fitness', 'LA Fitness', 'Quest Diagnostics'],
        'Personal Care' => ['Supercuts', 'Sephora', 'Ulta Beauty', 'The Barber Shop'],
        'Gifts' => ['Etsy', 'Amazon', 'Tiffany & Co.', 'Local Florist'],
        'Travel' => ['Booking.com', 'Airbnb', 'Delta Air Lines', 'Marriott', 'Expedia', 'Hertz'],
        'Education' => ['Udemy', 'Coursera', 'O\'Reilly Media', 'Amazon Books'],
        'Utilities' => ['ConEdison', 'AT&T', 'Xfinity', 'National Grid', 'Verizon Wireless'],
        'Subscriptions' => ['Netflix', 'Spotify', 'YouTube Premium', 'iCloud+', 'Adobe Creative Cloud', 'GitHub', 'ChatGPT Plus'],
        'Other Expenses' => ['PayPal', 'Venmo', 'Cash Withdrawal', 'Square'],
    ];

    public function run(): void
    {
        mt_srand(20260605);

        $this->createUsers();

        $usd = Currency::where('code', 'USD')->first();
        $eur = Currency::where('code', 'EUR')->first();

        if (! $usd) {
            $this->command->error('Run CurrencySeeder first (USD missing).');

            return;
        }

        $this->resetDemoData();

        $accounts = $this->createAccounts($usd, $eur);
        $expenseCategories = Category::where('type', 'expense')->get();
        $incomeCategories = Category::where('type', 'income')->get();
        $tags = Tag::all();

        $this->createTransactions($accounts, $expenseCategories, $incomeCategories, $tags);
        $this->createBudgets($usd, $expenseCategories, $tags);
        $this->createRecurringTransactions($accounts, $expenseCategories, $incomeCategories);
        $this->createAutomationRules($tags);

        mt_srand();

        $this->command->info('Demo data seeded: '.Transaction::count().' transactions across '.count($accounts).' accounts.');
    }

    private function createUsers(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@savvy.app'],
            ['name' => 'Alex Morgan', 'password' => 'password', 'role' => UserRole::Admin]
        );

        User::updateOrCreate(
            ['email' => 'editor@savvy.app'],
            ['name' => 'Jordan Lee', 'password' => 'password', 'role' => UserRole::ReadWrite]
        );

        User::updateOrCreate(
            ['email' => 'demo@demo.com'],
            ['name' => 'Demo User', 'password' => 'demo', 'role' => UserRole::ReadOnly]
        );

        $this->command->info('Users: admin@savvy.app / editor@savvy.app (password) · demo@demo.com (demo)');
    }

    private function resetDemoData(): void
    {
        DB::table('transaction_tag')->delete();
        DB::table('budget_tag')->delete();
        DB::table('budget_category')->delete();
        Transaction::query()->delete();
        Budget::query()->delete();
        RecurringTransaction::query()->delete();
        AutomationRule::query()->delete();
        Account::query()->delete();
    }

    private function createAccounts(Currency $usd, ?Currency $eur): array
    {
        $accounts = [];

        $accounts['checking'] = Account::create([
            'name' => 'Chase Checking', 'type' => 'bank', 'currency_id' => $usd->id,
            'initial_balance' => 8200.00, 'is_active' => true,
        ]);

        $accounts['cash'] = Account::create([
            'name' => 'Cash Wallet', 'type' => 'cash', 'currency_id' => $usd->id,
            'initial_balance' => 340.00, 'is_active' => true,
        ]);

        $accounts['savings'] = Account::create([
            'name' => 'Ally Savings', 'type' => 'bank', 'currency_id' => $usd->id,
            'initial_balance' => 24500.00, 'is_active' => true,
        ]);

        $accounts['crypto'] = Account::create([
            'name' => 'Coinbase Portfolio', 'type' => 'crypto', 'currency_id' => $usd->id,
            'initial_balance' => 6300.00, 'is_active' => true,
        ]);

        if ($eur) {
            $accounts['eur'] = Account::create([
                'name' => 'Revolut EUR', 'type' => 'bank', 'currency_id' => $eur->id,
                'initial_balance' => 1850.00, 'is_active' => true,
            ]);
        }

        $accounts['credit'] = Account::create([
            'name' => 'Amex Gold Card', 'type' => 'debt', 'currency_id' => $usd->id,
            'initial_balance' => 0, 'is_active' => true,
            'debt_type' => DebtType::IOwe, 'target_amount' => 0,
            'counterparty' => 'American Express', 'debt_description' => 'Credit card balance',
            'is_paid_off' => false,
        ]);

        $accounts['mortgage'] = Account::create([
            'name' => 'Home Mortgage', 'type' => 'debt', 'currency_id' => $usd->id,
            'initial_balance' => 0, 'is_active' => true,
            'debt_type' => DebtType::IOwe, 'target_amount' => 285000.00,
            'due_date' => now()->addYears(25), 'is_paid_off' => false,
            'counterparty' => 'Wells Fargo Home Mortgage', 'debt_description' => '30-year fixed mortgage',
        ]);

        $accounts['loan_out'] = Account::create([
            'name' => 'Loan to Michael', 'type' => 'debt', 'currency_id' => $usd->id,
            'initial_balance' => 0, 'is_active' => true,
            'debt_type' => DebtType::OwedToMe, 'target_amount' => 1200.00,
            'due_date' => now()->addMonths(4), 'is_paid_off' => false,
            'counterparty' => 'Michael Chen', 'debt_description' => 'Helped with moving costs',
        ]);

        return $accounts;
    }

    private function createTransactions(array $accounts, $expenseCategories, $incomeCategories, $tags): void
    {
        $start = now()->copy()->subMonths(self::MONTHS_OF_HISTORY)->startOfMonth();
        $end = now()->copy();

        $this->seedIncome($accounts, $incomeCategories, $start, $end);
        $this->seedFixedExpenses($accounts, $expenseCategories, $start, $end);
        $this->seedVariableExpenses($accounts, $expenseCategories, $tags, $start, $end);
        $this->seedTransfersAndDebt($accounts, $start, $end);
    }

    private function seedIncome(array $accounts, $incomeCategories, Carbon $start, Carbon $end): void
    {
        $salary = $incomeCategories->firstWhere('name', 'Salary');
        $freelance = $incomeCategories->firstWhere('name', 'Freelance');
        $investments = $incomeCategories->firstWhere('name', 'Investments');
        $other = $incomeCategories->firstWhere('name', 'Other Income');

        $baseSalary = 6400;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            foreach ([5, 20] as $payDay) {
                $payday = $cursor->copy()->day(min($payDay, $cursor->daysInMonth));
                if ($payday->between($start, $end) && $salary) {
                    $raise = $cursor->diffInMonths($start) >= 7 ? 600 : 0;
                    Transaction::create([
                        'type' => TransactionType::Income, 'account_id' => $accounts['checking']->id,
                        'category_id' => $salary->id, 'amount' => $baseSalary + $raise + mt_rand(-40, 40),
                        'description' => 'Acme Corp — Payroll', 'date' => $payday,
                    ]);
                }
            }

            if ($freelance && mt_rand(1, 100) <= 65) {
                $d = $cursor->copy()->day(mt_rand(8, min(26, $cursor->daysInMonth)));
                if ($d->between($start, $end)) {
                    $vendor = ['Upwork payout', 'Fiverr withdrawal', 'Client invoice #'.mt_rand(1000, 9999)][mt_rand(0, 2)];
                    Transaction::create([
                        'type' => TransactionType::Income,
                        'account_id' => isset($accounts['eur']) && mt_rand(0, 1) ? $accounts['eur']->id : $accounts['checking']->id,
                        'category_id' => $freelance->id, 'amount' => mt_rand(450, 1900),
                        'description' => $vendor, 'date' => $d,
                    ]);
                }
            }

            if ($investments && $cursor->month % 3 === 1) {
                $d = $cursor->copy()->day(min(15, $cursor->daysInMonth));
                if ($d->between($start, $end)) {
                    Transaction::create([
                        'type' => TransactionType::Income, 'account_id' => $accounts['crypto']->id,
                        'category_id' => $investments->id, 'amount' => mt_rand(80, 420),
                        'description' => 'Quarterly dividend payout', 'date' => $d,
                    ]);
                }
            }

            $cursor->addMonthNoOverflow();
        }

        if ($other) {
            Transaction::create([
                'type' => TransactionType::Income, 'account_id' => $accounts['checking']->id,
                'category_id' => $other->id, 'amount' => mt_rand(900, 1400),
                'description' => 'Tax refund — IRS', 'date' => now()->copy()->subMonths(2)->day(12),
            ]);
        }
    }

    private function seedFixedExpenses(array $accounts, $expenseCategories, Carbon $start, Carbon $end): void
    {
        $housing = $expenseCategories->firstWhere('name', 'Housing');
        $utilities = $expenseCategories->firstWhere('name', 'Utilities');
        $subscriptions = $expenseCategories->firstWhere('name', 'Subscriptions');

        $subs = [
            ['Netflix', 22.99], ['Spotify', 11.99], ['iCloud+', 9.99],
            ['Adobe Creative Cloud', 59.99], ['ChatGPT Plus', 20.00], ['GitHub', 4.00],
        ];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($housing) {
                $d = $cursor->copy()->startOfMonth();
                if ($d->between($start, $end)) {
                    Transaction::create([
                        'type' => TransactionType::Expense, 'account_id' => $accounts['checking']->id,
                        'category_id' => $housing->id, 'amount' => 2150.00,
                        'description' => 'Rent — Greystar Apartments', 'date' => $d,
                    ]);
                }
            }

            if ($utilities) {
                foreach (['ConEdison' => [70, 180], 'Xfinity Internet' => [79, 79], 'National Grid Gas' => [40, 120]] as $name => [$lo, $hi]) {
                    $d = $cursor->copy()->day(min(mt_rand(12, 18), $cursor->daysInMonth));
                    if ($d->between($start, $end)) {
                        $winter = in_array($cursor->month, [12, 1, 2]) ? 1.4 : 1.0;
                        Transaction::create([
                            'type' => TransactionType::Expense, 'account_id' => $accounts['checking']->id,
                            'category_id' => $utilities->id, 'amount' => round(mt_rand($lo, $hi) * $winter, 2),
                            'description' => $name, 'date' => $d,
                        ]);
                    }
                }
            }

            if ($subscriptions) {
                foreach ($subs as [$name, $price]) {
                    $d = $cursor->copy()->day(min(mt_rand(2, 9), $cursor->daysInMonth));
                    if ($d->between($start, $end)) {
                        Transaction::create([
                            'type' => TransactionType::Expense, 'account_id' => $accounts['credit']->id,
                            'category_id' => $subscriptions->id, 'amount' => $price,
                            'description' => $name.' subscription', 'date' => $d,
                        ]);
                    }
                }
            }

            $cursor->addMonthNoOverflow();
        }
    }

    private function seedVariableExpenses(array $accounts, $expenseCategories, $tags, Carbon $start, Carbon $end): void
    {
        $weights = [
            'Food & Groceries' => 22, 'Restaurants & Cafes' => 20, 'Transport' => 16,
            'Shopping' => 12, 'Entertainment' => 9, 'Healthcare' => 6, 'Personal Care' => 5,
            'Travel' => 3, 'Education' => 3, 'Gifts' => 2, 'Other Expenses' => 2,
        ];
        $pool = [];
        foreach ($weights as $name => $w) {
            $cat = $expenseCategories->firstWhere('name', $name);
            if ($cat) {
                for ($i = 0; $i < $w; $i++) {
                    $pool[] = $cat;
                }
            }
        }

        $ranges = [
            'Food & Groceries' => [18, 145], 'Restaurants & Cafes' => [9, 70], 'Transport' => [4, 65],
            'Shopping' => [15, 240], 'Entertainment' => [12, 95], 'Healthcare' => [10, 160],
            'Personal Care' => [15, 85], 'Travel' => [120, 950], 'Education' => [12, 90],
            'Gifts' => [20, 180], 'Other Expenses' => [10, 120],
        ];

        $vacationTag = $tags->firstWhere('name', 'Vacation');
        $essentialTag = $tags->firstWhere('name', 'Essential');
        $businessTag = $tags->firstWhere('name', 'Business');

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $isWeekend = $cursor->isWeekend();
            $seasonal = in_array($cursor->month, [11, 12]) ? 1.5 : ($cursor->month === 7 ? 1.25 : 1.0);

            $count = (int) round(($isWeekend ? mt_rand(2, 5) : mt_rand(1, 3)) * $seasonal);

            for ($i = 0; $i < $count; $i++) {
                $cat = $pool[array_rand($pool)];

                if ($cat->name === 'Travel' && mt_rand(1, 100) > 12) {
                    continue;
                }

                [$lo, $hi] = $ranges[$cat->name] ?? [10, 80];
                $amount = round(mt_rand($lo * 100, $hi * 100) / 100 * $seasonal, 2);
                $merchant = $this->merchantFor($cat->name);

                $account = match (true) {
                    $cat->name === 'Travel' && isset($accounts['eur']) && mt_rand(0, 1) === 1 => $accounts['eur'],
                    in_array($cat->name, ['Food & Groceries', 'Shopping', 'Travel', 'Healthcare']) && mt_rand(1, 100) <= 60 => $accounts['credit'],
                    $amount < 25 && mt_rand(1, 100) <= 35 => $accounts['cash'],
                    default => $accounts['checking'],
                };

                $tx = Transaction::create([
                    'type' => TransactionType::Expense, 'account_id' => $account->id,
                    'category_id' => $cat->id, 'amount' => $amount, 'description' => $merchant,
                    'date' => $cursor->copy()->setTime(mt_rand(7, 22), mt_rand(0, 59)),
                ]);

                $attach = [];
                if ($cat->name === 'Travel' && $vacationTag) {
                    $attach[] = $vacationTag->id;
                }
                if (in_array($cat->name, ['Food & Groceries', 'Healthcare']) && $essentialTag && mt_rand(1, 100) <= 35) {
                    $attach[] = $essentialTag->id;
                }
                if ($cat->name === 'Education' && $businessTag && mt_rand(1, 100) <= 50) {
                    $attach[] = $businessTag->id;
                }
                if ($attach) {
                    $tx->tags()->attach(array_unique($attach));
                }
            }

            $cursor->addDay();
        }
    }

    private function seedTransfersAndDebt(array $accounts, Carbon $start, Carbon $end): void
    {
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $d = $cursor->copy()->day(min(6, $cursor->daysInMonth));
            if ($d->between($start, $end)) {
                Transaction::create([
                    'type' => TransactionType::Transfer, 'account_id' => $accounts['checking']->id,
                    'to_account_id' => $accounts['savings']->id, 'amount' => 800, 'to_amount' => 800,
                    'description' => 'Automatic transfer to savings', 'date' => $d,
                ]);
            }

            $dCard = $cursor->copy()->day(min(25, $cursor->daysInMonth));
            if ($dCard->between($start, $end)) {
                $pay = mt_rand(600, 1400);
                Transaction::create([
                    'type' => TransactionType::DebtPayment, 'account_id' => $accounts['checking']->id,
                    'to_account_id' => $accounts['credit']->id, 'amount' => $pay, 'to_amount' => $pay,
                    'description' => 'Amex statement payment', 'date' => $dCard,
                ]);
            }

            $dMort = $cursor->copy()->day(min(2, $cursor->daysInMonth));
            if ($dMort->between($start, $end)) {
                Transaction::create([
                    'type' => TransactionType::DebtPayment, 'account_id' => $accounts['checking']->id,
                    'to_account_id' => $accounts['mortgage']->id, 'amount' => 1680.00, 'to_amount' => 1680.00,
                    'description' => 'Monthly mortgage payment', 'date' => $dMort,
                ]);
            }

            if (isset($accounts['eur']) && $cursor->month % 2 === 0) {
                $dFx = $cursor->copy()->day(min(14, $cursor->daysInMonth));
                if ($dFx->between($start, $end)) {
                    $usdOut = mt_rand(300, 600);
                    Transaction::create([
                        'type' => TransactionType::Transfer, 'account_id' => $accounts['checking']->id,
                        'to_account_id' => $accounts['eur']->id, 'amount' => $usdOut,
                        'to_amount' => round($usdOut / 1.08, 2), 'exchange_rate' => round(1 / 1.08, 6),
                        'description' => 'USD → EUR top-up', 'date' => $dFx,
                    ]);
                }
            }

            $cursor->addMonthNoOverflow();
        }

        Transaction::create([
            'type' => TransactionType::DebtCollection, 'account_id' => $accounts['checking']->id,
            'to_account_id' => $accounts['loan_out']->id, 'amount' => 400, 'to_amount' => 400,
            'description' => 'Michael — partial repayment', 'date' => now()->copy()->subMonths(2)->day(18),
        ]);
    }

    private function merchantFor(string $category): string
    {
        $list = $this->merchants[$category] ?? ['General Store'];

        return $list[array_rand($list)];
    }

    private function createBudgets(Currency $usd, $expenseCategories, $tags): void
    {
        $food = $expenseCategories->firstWhere('name', 'Food & Groceries');
        if ($food) {
            $b = Budget::create([
                'name' => 'Groceries', 'amount' => 750, 'currency_id' => $usd->id,
                'period' => BudgetPeriod::Monthly, 'start_date' => now()->startOfMonth(),
                'is_global' => false, 'notify_at_percent' => 80, 'is_active' => true,
            ]);
            $b->categories()->sync([$food->id]);
        }

        $dining = $expenseCategories->firstWhere('name', 'Restaurants & Cafes');
        if ($dining) {
            $b = Budget::create([
                'name' => 'Dining Out', 'amount' => 450, 'currency_id' => $usd->id,
                'period' => BudgetPeriod::Monthly, 'start_date' => now()->startOfMonth(),
                'is_global' => false, 'notify_at_percent' => 90, 'is_active' => true,
            ]);
            $b->categories()->sync([$dining->id]);
        }

        $ent = $expenseCategories->firstWhere('name', 'Entertainment');
        if ($ent) {
            $b = Budget::create([
                'name' => 'Fun Money', 'amount' => 300, 'currency_id' => $usd->id,
                'period' => BudgetPeriod::Monthly, 'start_date' => now()->startOfMonth(),
                'is_global' => false, 'notify_at_percent' => 100, 'is_active' => true,
            ]);
            $b->categories()->sync([$ent->id]);
        }

        Budget::create([
            'name' => 'Total Monthly Spending', 'amount' => 5500, 'currency_id' => $usd->id,
            'period' => BudgetPeriod::Monthly, 'start_date' => now()->startOfMonth(),
            'is_global' => true, 'notify_at_percent' => 85, 'is_active' => true,
        ]);

        $vacation = $tags->firstWhere('name', 'Vacation');
        if ($vacation) {
            $b = Budget::create([
                'name' => 'Italy Trip 2026', 'amount' => 3500, 'currency_id' => $usd->id,
                'period' => BudgetPeriod::OneTime, 'start_date' => now()->subMonth(),
                'end_date' => now()->addMonths(4), 'is_global' => false,
                'notify_at_percent' => 75, 'is_active' => true,
            ]);
            $b->tags()->sync([$vacation->id]);
        }
    }

    private function createRecurringTransactions(array $accounts, $expenseCategories, $incomeCategories): void
    {
        $salary = $incomeCategories->firstWhere('name', 'Salary');
        if ($salary) {
            RecurringTransaction::create([
                'type' => TransactionType::Income, 'account_id' => $accounts['checking']->id,
                'category_id' => $salary->id, 'amount' => 7000, 'description' => 'Acme Corp — Payroll',
                'frequency' => RecurringFrequency::Monthly, 'interval' => 1, 'day_of_month' => 5,
                'start_date' => now()->startOfMonth(), 'next_run_date' => now()->day(5)->addMonthNoOverflow(),
                'is_active' => true,
            ]);
        }

        $housing = $expenseCategories->firstWhere('name', 'Housing');
        if ($housing) {
            RecurringTransaction::create([
                'type' => TransactionType::Expense, 'account_id' => $accounts['checking']->id,
                'category_id' => $housing->id, 'amount' => 2150, 'description' => 'Rent — Greystar Apartments',
                'frequency' => RecurringFrequency::Monthly, 'interval' => 1, 'day_of_month' => 1,
                'start_date' => now()->startOfMonth(), 'next_run_date' => now()->addMonth()->startOfMonth(),
                'is_active' => true,
            ]);
        }

        $subs = $expenseCategories->firstWhere('name', 'Subscriptions');
        if ($subs) {
            RecurringTransaction::create([
                'type' => TransactionType::Expense, 'account_id' => $accounts['credit']->id,
                'category_id' => $subs->id, 'amount' => 22.99, 'description' => 'Netflix subscription',
                'frequency' => RecurringFrequency::Monthly, 'interval' => 1, 'day_of_month' => 4,
                'start_date' => now()->startOfMonth(), 'next_run_date' => now()->day(4)->addMonthNoOverflow(),
                'is_active' => true,
            ]);
        }

        RecurringTransaction::create([
            'type' => TransactionType::Transfer, 'account_id' => $accounts['checking']->id,
            'to_account_id' => $accounts['savings']->id, 'amount' => 800, 'to_amount' => 800,
            'description' => 'Automatic transfer to savings',
            'frequency' => RecurringFrequency::Monthly, 'interval' => 1, 'day_of_month' => 6,
            'start_date' => now()->startOfMonth(), 'next_run_date' => now()->day(6)->addMonthNoOverflow(),
            'is_active' => true,
        ]);

        RecurringTransaction::create([
            'type' => TransactionType::Transfer, 'account_id' => $accounts['checking']->id,
            'to_account_id' => $accounts['mortgage']->id, 'amount' => 1680, 'to_amount' => 1680,
            'description' => 'Monthly mortgage payment',
            'frequency' => RecurringFrequency::Monthly, 'interval' => 1, 'day_of_month' => 2,
            'start_date' => now()->startOfMonth(), 'next_run_date' => now()->day(2)->addMonthNoOverflow(),
            'is_active' => true,
        ]);
    }

    private function createAutomationRules($tags): void
    {
        $essential = $tags->firstWhere('name', 'Essential');
        $recurring = $tags->firstWhere('name', 'Recurring');
        $business = $tags->firstWhere('name', 'Business');

        AutomationRule::create([
            'name' => 'Flag large transactions', 'description' => 'Tag any transaction above $500 as Essential',
            'trigger_type' => TriggerType::OnTransactionCreate, 'priority' => 1,
            'conditions' => ['match' => 'all', 'conditions' => [['field' => 'amount', 'op' => 'gt', 'value' => 500]]],
            'actions' => $essential ? [['type' => 'add_tags', 'tag_ids' => [$essential->id]]] : [],
            'is_active' => true, 'stop_processing' => false,
        ]);

        if ($recurring) {
            AutomationRule::create([
                'name' => 'Tag subscriptions', 'description' => 'Tag transactions containing "subscription"',
                'trigger_type' => TriggerType::OnTransactionCreate, 'priority' => 2,
                'conditions' => ['match' => 'all', 'conditions' => [['field' => 'description', 'op' => 'contains', 'value' => 'subscription']]],
                'actions' => [['type' => 'add_tags', 'tag_ids' => [$recurring->id]]],
                'is_active' => true, 'stop_processing' => false,
            ]);
        }

        if ($business) {
            AutomationRule::create([
                'name' => 'Tag software tools', 'description' => 'Tag GitHub / Adobe / Udemy as Business',
                'trigger_type' => TriggerType::OnTransactionCreate, 'priority' => 3,
                'conditions' => ['match' => 'any', 'conditions' => [
                    ['field' => 'description', 'op' => 'contains', 'value' => 'GitHub'],
                    ['field' => 'description', 'op' => 'contains', 'value' => 'Adobe'],
                    ['field' => 'description', 'op' => 'contains', 'value' => 'Udemy'],
                ]],
                'actions' => [['type' => 'add_tags', 'tag_ids' => [$business->id]]],
                'is_active' => true, 'stop_processing' => false,
            ]);
        }
    }
}
