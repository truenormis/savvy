<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\StoreRecurringRequest;
use App\Http\Requests\Recurring\UpdateRecurringRequest;
use App\Http\Resources\RecurringTransactionResource;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecurringTransactionController extends Controller
{
    public function __construct(
        private RecurringTransactionService $service
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return RecurringTransactionResource::collection(
            $this->service->getAll()
        );
    }

    public function store(StoreRecurringRequest $request): JsonResponse
    {
        $recurring = $this->service->create($request->validated());

        return (new RecurringTransactionResource($recurring))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RecurringTransaction $recurring): RecurringTransactionResource
    {
        return new RecurringTransactionResource(
            $this->service->findOrFail($recurring->id)
        );
    }

    public function update(UpdateRecurringRequest $request, RecurringTransaction $recurring): RecurringTransactionResource
    {
        return new RecurringTransactionResource(
            $this->service->update($recurring, $request->validated())
        );
    }

    public function destroy(RecurringTransaction $recurring): JsonResponse
    {
        $this->service->delete($recurring);

        return response()->json(null, 204);
    }

    public function skip(RecurringTransaction $recurring): RecurringTransactionResource
    {
        return new RecurringTransactionResource(
            $this->service->skip($recurring)
        );
    }

    public function upcoming(): AnonymousResourceCollection
    {
        return RecurringTransactionResource::collection(
            $this->service->getUpcoming(5)
        );
    }
}
