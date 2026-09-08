<?php

use App\Enums\DebtType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\DebtService;
use App\Services\Reports\NetWorthReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function debtCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );
}

function cashAccount(float $initial = 0): Account
{
    return Account::create([
        'name' => 'Checking',
        'type' => 'bank',
        'currency_id' => debtCurrency()->id,
        'initial_balance' => $initial,
        'is_active' => true,
    ]);
}

function debtAccount(string $name, DebtType $type, float $target): Account
{
    return Account::create([
        'name' => $name,
        'type' => 'debt',
        'currency_id' => debtCurrency()->id,
        'initial_balance' => 0,
        'is_active' => true,
        'debt_type' => $type,
        'target_amount' => $target,
        'is_paid_off' => false,
    ]);
}

function chargeTo(Account $debt, float $amount): void
{
    Transaction::create([
        'type' => 'expense',
        'account_id' => $debt->id,
        'amount' => $amount,
        'description' => 'card purchase',
        'date' => '2026-09-01',
    ]);
}

function payTowards(Account $from, Account $debt, float $amount): void
{
    Transaction::create([
        'type' => 'debt_payment',
        'account_id' => $from->id,
        'to_account_id' => $debt->id,
        'amount' => $amount,
        'to_amount' => $amount,
        'description' => 'statement payment',
        'date' => '2026-09-02',
    ]);
}

describe('credit card style debt', function () {
    it('grows the debt by what was charged to the account', function () {
        $card = debtAccount('Card', DebtType::IOwe, 0);
        chargeTo($card, 300);
        chargeTo($card, 200);

        expect($card->fresh()->current_balance)->toBe(500.0);
    });

    it('nets charges against payments instead of going negative', function () {
        $checking = cashAccount(1000);
        $card = debtAccount('Card', DebtType::IOwe, 0);

        chargeTo($card, 500);
        payTowards($checking, $card, 200);

        expect($card->fresh()->current_balance)->toBe(300.0);
    });

    it('reports payment progress against everything ever charged', function () {
        $checking = cashAccount(1000);
        $card = debtAccount('Card', DebtType::IOwe, 0);

        chargeTo($card, 400);
        payTowards($checking, $card, 100);

        expect($card->fresh()->payment_progress)->toBe(25.0);
    });

    it('treats income booked on the debt as a credit', function () {
        $card = debtAccount('Card', DebtType::IOwe, 0);
        chargeTo($card, 300);

        Transaction::create([
            'type' => 'income',
            'account_id' => $card->id,
            'amount' => 50,
            'description' => 'merchant refund',
            'date' => '2026-09-03',
        ]);

        expect($card->fresh()->current_balance)->toBe(250.0);
    });
});

describe('fixed loan debt', function () {
    it('still counts down from the opening amount', function () {
        $checking = cashAccount(10000);
        $loan = debtAccount('Mortgage', DebtType::IOwe, 285000);

        payTowards($checking, $loan, 21840);

        expect($loan->fresh()->current_balance)->toBe(263160.0)
            ->and($loan->fresh()->payment_progress)->toBe(7.66);
    });

    it('counts down an owed-to-me debt as it is collected', function () {
        $checking = cashAccount(0);
        $loan = debtAccount('Loan to Michael', DebtType::OwedToMe, 1200);

        Transaction::create([
            'type' => 'debt_collection',
            'account_id' => $checking->id,
            'to_account_id' => $loan->id,
            'amount' => 400,
            'to_amount' => 400,
            'description' => 'partial repayment',
            'date' => '2026-09-04',
        ]);

        expect($loan->fresh()->current_balance)->toBe(800.0);
    });
});

describe('debt summary', function () {
    it('adds a card balance to what is owed instead of subtracting it', function () {
        $checking = cashAccount(1000);
        $card = debtAccount('Card', DebtType::IOwe, 0);
        $loan = debtAccount('Mortgage', DebtType::IOwe, 285000);

        chargeTo($card, 34105.52);
        payTowards($checking, $card, 11738);
        payTowards($checking, $loan, 21840);

        $summary = app(DebtService::class)->getSummary();

        expect($summary['total_i_owe'])->toBe(285527.52);
    });

    it('does not let an overpaid debt reduce the total owed', function () {
        $checking = cashAccount(10000);
        $overpaid = debtAccount('Overpaid', DebtType::IOwe, 100);
        $other = debtAccount('Other', DebtType::IOwe, 500);

        payTowards($checking, $overpaid, 400);

        $summary = app(DebtService::class)->getSummary();

        expect($summary['total_i_owe'])->toBe(500.0);
    });
});

describe('net worth', function () {
    it('subtracts what is owed from the assets', function () {
        $checking = cashAccount(100000);
        $loan = debtAccount('Mortgage', DebtType::IOwe, 60000);

        $filters = App\DTOs\ReportFilterData::fromArray([
            'period_type' => 'month',
            'month' => '2026-09',
            'compare_with' => 'none',
        ]);

        $result = app(NetWorthReportService::class)->getCurrent($filters);

        expect($result['current'])->toBe(40000.0);
    });

    it('adds a debt owed to you to the assets', function () {
        cashAccount(1000);
        debtAccount('Loan out', DebtType::OwedToMe, 250);

        $filters = App\DTOs\ReportFilterData::fromArray([
            'period_type' => 'month',
            'month' => '2026-09',
            'compare_with' => 'none',
        ]);

        $result = app(NetWorthReportService::class)->getCurrent($filters);

        expect($result['current'])->toBe(1250.0);
    });
});
