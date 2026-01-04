<?php

namespace App\Http\Controllers;

use App\Http\Requests\Currency\StoreCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Services\CurrencyService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection($this->currencyService->getAll());
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = $this->currencyService->create($request->validated());

        return (new CurrencyResource($currency))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Currency $currency): CurrencyResource
    {
        return new CurrencyResource($currency);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource|JsonResponse
    {
        try {
            $currency = $this->currencyService->update($currency, $request->validated());

            return new CurrencyResource($currency);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Currency $currency): JsonResponse
    {
        try {
            $this->currencyService->delete($currency);

            return response()->json(null, 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function setBase(Currency $currency): CurrencyResource|JsonResponse
    {
        try {
            $currency = $this->currencyService->setAsBase($currency);

            return new CurrencyResource($currency);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
        ]);

        $from = $this->currencyService->findOrFail($validated['from_currency_id']);
        $to = $this->currencyService->findOrFail($validated['to_currency_id']);

        $result = $this->currencyService->convert(
            (float) $validated['amount'],
            $from,
            $to
        );

        return response()->json([
            'amount' => $validated['amount'],
            'from' => new CurrencyResource($from),
            'to' => new CurrencyResource($to),
            'result' => round($result, $to->decimals),
        ]);
    }
}
