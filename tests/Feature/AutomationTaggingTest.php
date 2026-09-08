<?php

use App\Enums\TriggerType;
use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Currency;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function automationAccount(): Account
{
    $currency = Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );

    return Account::create([
        'name' => 'Checking',
        'type' => 'bank',
        'currency_id' => $currency->id,
        'initial_balance' => 10000,
        'is_active' => true,
    ]);
}

function tagRule(string $name, int $priority, array $conditions, string $type, array $tagIds): AutomationRule
{
    return AutomationRule::create([
        'name' => $name,
        'trigger_type' => TriggerType::OnTransactionCreate,
        'priority' => $priority,
        'conditions' => ['match' => 'all', 'conditions' => $conditions],
        'actions' => [['type' => $type, 'tag_ids' => $tagIds]],
        'is_active' => true,
        'stop_processing' => false,
    ]);
}

function makeTransaction(Account $account, float $amount, string $description): Transaction
{
    return Transaction::create([
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => $amount,
        'description' => $description,
        'date' => '2026-09-08',
    ]);
}

it('keeps the tags of every rule that matches, not just the last one', function () {
    $account = automationAccount();
    $essential = Tag::create(['name' => 'Essential']);
    $recurring = Tag::create(['name' => 'Recurring']);

    tagRule('Large', 1, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'add_tags', [$essential->id]);
    tagRule('Subscriptions', 2, [['field' => 'description', 'op' => 'contains', 'value' => 'subscription']], 'add_tags', [$recurring->id]);

    $transaction = makeTransaction($account, 600, 'Adobe subscription');

    app(AutomationService::class)->process(TriggerType::OnTransactionCreate, $transaction);

    expect($transaction->fresh()->tags->pluck('name')->sort()->values()->all())
        ->toBe(['Essential', 'Recurring']);
});

it('keeps tags the user set by hand when a rule adds its own', function () {
    $account = automationAccount();
    $manual = Tag::create(['name' => 'Personal']);
    $auto = Tag::create(['name' => 'Essential']);

    tagRule('Large', 1, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'add_tags', [$auto->id]);

    $transaction = makeTransaction($account, 600, 'Something big');
    $transaction->tags()->attach($manual->id);

    app(AutomationService::class)->process(TriggerType::OnTransactionCreate, $transaction->fresh());

    expect($transaction->fresh()->tags->pluck('name')->sort()->values()->all())
        ->toBe(['Essential', 'Personal']);
});

it('does not duplicate a tag the transaction already carries', function () {
    $account = automationAccount();
    $tag = Tag::create(['name' => 'Essential']);

    tagRule('Large', 1, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'add_tags', [$tag->id]);

    $transaction = makeTransaction($account, 600, 'Something big');
    $transaction->tags()->attach($tag->id);

    app(AutomationService::class)->process(TriggerType::OnTransactionCreate, $transaction->fresh());

    expect($transaction->fresh()->tags)->toHaveCount(1);
});

it('removes only the tags the rule names', function () {
    $account = automationAccount();
    $keep = Tag::create(['name' => 'Personal']);
    $drop = Tag::create(['name' => 'Essential']);

    tagRule('Untag', 1, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'remove_tags', [$drop->id]);

    $transaction = makeTransaction($account, 600, 'Something big');
    $transaction->tags()->attach([$keep->id, $drop->id]);

    app(AutomationService::class)->process(TriggerType::OnTransactionCreate, $transaction->fresh());

    expect($transaction->fresh()->tags->pluck('name')->all())->toBe(['Personal']);
});

it('applies a later add rule on top of an earlier remove rule', function () {
    $account = automationAccount();
    $first = Tag::create(['name' => 'Essential']);
    $second = Tag::create(['name' => 'Recurring']);

    tagRule('Untag', 1, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'remove_tags', [$first->id]);
    tagRule('Tag', 2, [['field' => 'amount', 'op' => 'gt', 'value' => 500]], 'add_tags', [$second->id]);

    $transaction = makeTransaction($account, 600, 'Something big');
    $transaction->tags()->attach($first->id);

    app(AutomationService::class)->process(TriggerType::OnTransactionCreate, $transaction->fresh());

    expect($transaction->fresh()->tags->pluck('name')->all())->toBe(['Recurring']);
});
