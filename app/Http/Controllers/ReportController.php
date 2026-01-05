<?php

namespace App\Http\Controllers;

use App\DTOs\ReportFilterData;
use App\Http\Requests\Report\CashFlowRequest;
use App\Http\Requests\Report\OverviewRequest;
use App\Http\Requests\Report\TransactionReportRequest;
use App\Services\Reports\CashFlowReportService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\NetWorthReportService;
use App\Services\Reports\OverviewReportService;
use App\Services\Reports\TransactionReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private OverviewReportService $overviewService,
        private CashFlowReportService $cashFlowService,
        private ExpenseReportService $expenseService,
        private TransactionReportService $transactionService,
        private NetWorthReportService $netWorthService
    ) {}

    public function overview(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->overviewService->getMetrics($filters));
    }

    public function moneyFlow(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->cashFlowService->getMoneyFlow($filters));
    }

    public function expensePace(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->expenseService->getPace($filters));
    }

    public function expensesByCategory(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->expenseService->getByCategory($filters));
    }

    public function cashFlowOverTime(CashFlowRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $groupBy = $request->validated('group_by', 'day');
        return response()->json($this->cashFlowService->getOverTime($filters, $groupBy));
    }

    public function activityHeatmap(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->expenseService->getHeatmap($filters));
    }

    public function transactionSummary(TransactionReportRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $type = $request->validated('type');
        return response()->json($this->transactionService->getSummary($filters, $type));
    }

    public function transactionsByCategory(TransactionReportRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $type = $request->validated('type');
        return response()->json($this->transactionService->getByCategory($filters, $type));
    }

    public function transactionDynamics(TransactionReportRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $type = $request->validated('type');
        $groupBy = $request->validated('group_by', 'day');
        return response()->json($this->transactionService->getDynamics($filters, $type, $groupBy));
    }

    public function topTransactions(TransactionReportRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $type = $request->validated('type');
        $limit = $request->validated('limit', 10);
        return response()->json($this->transactionService->getTop($filters, $type, $limit));
    }

    public function netWorth(OverviewRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        return response()->json($this->netWorthService->getCurrent($filters));
    }

    public function netWorthHistory(CashFlowRequest $request): JsonResponse
    {
        $filters = ReportFilterData::fromArray($request->validated());
        $groupBy = $request->validated('group_by', 'day');
        return response()->json($this->netWorthService->getHistory($filters, $groupBy));
    }
}
