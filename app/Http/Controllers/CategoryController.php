<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Currency;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $type = $request->input('type');

        return CategoryResource::collection($this->categoryService->getAll($type));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($this->categoryService->findOrFail($category->id));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->categoryService->update($category, $request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category);

            return response()->json(null, 204);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function statistics(Request $request, Category $category): JsonResponse
    {
        $stats = $this->categoryService->getStatistics(
            $category->id,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($stats);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:income,expense',
        ]);

        $summary = $this->categoryService->getSummaryByType(
            $request->input('type'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        $baseCurrency = Currency::getBase();

        return response()->json([
            'data' => CategoryResource::collection($summary),
            'total' => $summary->sum('total_amount'),
            'currency' => $baseCurrency?->symbol ?? '',
        ]);
    }
}
