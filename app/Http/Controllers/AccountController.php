<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountCollection;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accountService
    ) {}

    public function index(Request $request): AccountCollection
    {
        $onlyActive = $request->boolean('active');
        $excludeDebts = $request->boolean('exclude_debts');
        $accounts = $this->accountService->getAll($onlyActive, $excludeDebts);

        $collection = new AccountCollection($accounts);

        if ($request->boolean('with_summary')) {
            $baseCurrencyId = $request->has('base_currency_id')
                ? (int) $request->input('base_currency_id')
                : null;

            $summary = $this->accountService->getSummary($baseCurrencyId);
            $collection->additional(['summary' => $summary]);
        }

        return $collection;
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->validated());

        return (new AccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account): AccountResource
    {
        return new AccountResource($this->accountService->findOrFail($account->id));
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $account = $this->accountService->update($account, $request->validated());

        return new AccountResource($account);
    }

    public function destroy(Account $account): JsonResponse
    {
        try {
            $this->accountService->delete($account);

            return response()->json(null, 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function balanceHistory(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $history = $this->accountService->getBalanceHistory($startDate, $endDate);

        return response()->json($history);
    }
}
