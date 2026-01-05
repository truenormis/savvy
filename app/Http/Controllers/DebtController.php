<?php

namespace App\Http\Controllers;

use App\DTOs\DebtData;
use App\DTOs\DebtPaymentData;
use App\Http\Requests\Debt\DebtPaymentRequest;
use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Http\Resources\DebtCollection;
use App\Http\Resources\DebtResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Services\DebtService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function __construct(
        private DebtService $debtService
    ) {}

    /**
     * GET /api/debts
     */
    public function index(Request $request): DebtCollection
    {
        $includeCompleted = $request->boolean('include_completed');
        $debts = $this->debtService->getAll($includeCompleted);

        $collection = new DebtCollection($debts);

        if ($request->boolean('with_summary')) {
            $collection->additional(['summary' => $this->debtService->getSummary()]);
        }

        return $collection;
    }

    /**
     * POST /api/debts
     */
    public function store(StoreDebtRequest $request): JsonResponse
    {
        $data = DebtData::fromArray($request->validated());
        $debt = $this->debtService->create($data);

        return (new DebtResource($debt))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/debts/{debt}
     */
    public function show(Account $debt): DebtResource|JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        $debt->load('currency');
        $debt->setAttribute('current_balance', $debt->current_balance);
        $debt->setAttribute('remaining_debt', $debt->remaining_debt);
        $debt->setAttribute('payment_progress', $debt->payment_progress);

        return new DebtResource($debt);
    }

    /**
     * PUT /api/debts/{debt}
     */
    public function update(UpdateDebtRequest $request, Account $debt): DebtResource|JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        $validated = $request->validated();
        $data = DebtData::fromArray([
            'name' => $validated['name'] ?? $debt->name,
            'debt_type' => $validated['debt_type'] ?? $debt->debt_type,
            'currency_id' => $validated['currency_id'] ?? $debt->currency_id,
            'amount' => $validated['amount'] ?? $debt->target_amount,
            'due_date' => $validated['due_date'] ?? $debt->due_date?->toDateString(),
            'counterparty' => $validated['counterparty'] ?? $debt->counterparty,
            'description' => $validated['description'] ?? $debt->debt_description,
        ]);

        $debt = $this->debtService->update($debt, $data);

        return new DebtResource($debt);
    }

    /**
     * DELETE /api/debts/{debt}
     */
    public function destroy(Account $debt): JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        try {
            $this->debtService->delete($debt);

            return response()->json(null, 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/debts/{debt}/payment
     */
    public function payment(DebtPaymentRequest $request, Account $debt): TransactionResource|JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        try {
            $paymentData = DebtPaymentData::fromArray([
                'debt_id' => $debt->id,
                ...$request->validated(),
            ]);

            $sourceAccount = Account::findOrFail($request->input('account_id'));
            $transaction = $this->debtService->makePayment($debt, $sourceAccount, $paymentData);

            return new TransactionResource($transaction);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/debts/{debt}/collect
     */
    public function collect(DebtPaymentRequest $request, Account $debt): TransactionResource|JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        try {
            $paymentData = DebtPaymentData::fromArray([
                'debt_id' => $debt->id,
                ...$request->validated(),
            ]);

            $targetAccount = Account::findOrFail($request->input('account_id'));
            $transaction = $this->debtService->collectPayment($debt, $targetAccount, $paymentData);

            return new TransactionResource($transaction);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/debts-summary
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->debtService->getSummary());
    }

    /**
     * POST /api/debts/{debt}/reopen
     */
    public function reopen(Account $debt): DebtResource|JsonResponse
    {
        if (!$debt->isDebt()) {
            return response()->json(['message' => 'Account is not a debt.'], 404);
        }

        try {
            $debt = $this->debtService->reopen($debt);

            return new DebtResource($debt);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
