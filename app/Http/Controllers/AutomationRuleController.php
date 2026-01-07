<?php

namespace App\Http\Controllers;

use App\Enums\TriggerType;
use App\Http\Requests\AutomationRuleRequest;
use App\Http\Resources\AutomationRuleResource;
use App\Http\Resources\AutomationRuleLogResource;
use App\Models\AutomationRule;
use App\Models\Transaction;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationRuleController extends Controller
{
    public function __construct(
        private AutomationService $service
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $rules = AutomationRule::query()
            ->ordered()
            ->get();

        return AutomationRuleResource::collection($rules);
    }

    public function store(AutomationRuleRequest $request): JsonResponse
    {
        $rule = AutomationRule::create($request->validated());

        return (new AutomationRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AutomationRule $automationRule): AutomationRuleResource
    {
        return new AutomationRuleResource($automationRule);
    }

    public function update(AutomationRuleRequest $request, AutomationRule $automationRule): AutomationRuleResource
    {
        $automationRule->update($request->validated());

        return new AutomationRuleResource($automationRule);
    }

    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $automationRule->delete();

        return response()->json(null, 204);
    }

    public function toggle(AutomationRule $automationRule): AutomationRuleResource
    {
        $automationRule->update([
            'is_active' => !$automationRule->is_active,
        ]);

        return new AutomationRuleResource($automationRule);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|exists:automation_rules,id',
            'rules.*.priority' => 'required|integer|min:1|max:100',
        ]);

        foreach ($request->input('rules') as $ruleData) {
            AutomationRule::where('id', $ruleData['id'])
                ->update(['priority' => $ruleData['priority']]);
        }

        return response()->json(['success' => true]);
    }

    public function test(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::with(['account', 'category', 'tags'])
            ->findOrFail($request->input('transaction_id'));

        $result = $this->service->testRule($automationRule, $transaction);

        return response()->json($result);
    }

    public function logs(AutomationRule $automationRule): AnonymousResourceCollection
    {
        $logs = $automationRule->logs()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return AutomationRuleLogResource::collection($logs);
    }

    public function triggers(): JsonResponse
    {
        $triggers = collect(TriggerType::cases())->map(fn (TriggerType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
        ]);

        return response()->json($triggers);
    }
}
