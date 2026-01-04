<?php

namespace App\Http\Controllers;

use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgetService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $budgets = $this->budgetService->getAll();

        $budgets->each(function ($budget) {
            $budget->progress = $this->budgetService->calculateProgress($budget);
        });

        return BudgetResource::collection($budgets);
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = $this->budgetService->create($request->validated());
        $budget->progress = $this->budgetService->calculateProgress($budget);

        return (new BudgetResource($budget))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Budget $budget): BudgetResource
    {
        $budget = $this->budgetService->findOrFail($budget->id);
        $budget->progress = $this->budgetService->calculateProgress($budget);

        return new BudgetResource($budget);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): BudgetResource
    {
        $budget = $this->budgetService->update($budget, $request->validated());
        $budget->progress = $this->budgetService->calculateProgress($budget);

        return new BudgetResource($budget);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->budgetService->delete($budget);

        return response()->json(null, 204);
    }
}
