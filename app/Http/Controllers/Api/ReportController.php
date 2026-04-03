<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\FinancialEntry;
use App\Models\Invoice;
use App\Models\InvoiceDeduction;
use App\Models\Person;
use App\Models\Project;
use App\Models\QidRenewal;
use App\Models\Settlement;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function assignedPersonIds(Request $request)
    {
        if (!$request->user()->hasRole('Collector')) {
            return null;
        }

        return $request->user()->assignedAccounts()->where('is_active', true)->pluck('person_id');
    }

    public function dashboardStats(Request $request)
    {
        $assignedPersonIds = $this->assignedPersonIds($request);
        $threshold = (int) Setting::getValue('qid.threshold_days', 30);

        $invoiceQuery = Invoice::with('collections', 'project:id,person_id')->whereIn('status', ['issued', 'partially_paid']);
        $collectionQuery = Collection::query();
        $projectQuery = Project::query();
        $personQuery = Person::whereNotNull('id_expiration_date');
        $renewalQuery = QidRenewal::query();

        if ($assignedPersonIds) {
            $invoiceQuery->whereHas('project', fn ($q) => $q->whereIn('person_id', $assignedPersonIds));
            $collectionQuery->whereHas('invoice.project', fn ($q) => $q->whereIn('person_id', $assignedPersonIds));
            $projectQuery->whereIn('person_id', $assignedPersonIds);
            $personQuery->whereIn('id', $assignedPersonIds);
            $renewalQuery->whereIn('person_id', $assignedPersonIds);
        }

        $pendingInvoices = $invoiceQuery->get();
        $pendingInvoiceTotal = $pendingInvoices->sum(fn ($invoice) => $invoice->outstanding_amount);
        $dueTodayInvoiceTotal = $pendingInvoices
            ->filter(fn ($invoice) => optional($invoice->due_date)?->toDateString() === now()->toDateString())
            ->sum(fn ($invoice) => $invoice->outstanding_amount);
        $overdueInvoiceTotal = $pendingInvoices
            ->filter(fn ($invoice) => $invoice->due_date && $invoice->due_date->lt(now()->startOfDay()))
            ->sum(fn ($invoice) => $invoice->outstanding_amount);

        $activeProjectsQuery = (clone $projectQuery)->whereNotIn('status', ['cancelled', 'financially_closed', 'archived']);
        $activeProjectCount = (clone $activeProjectsQuery)->count();
        $activeNotInvoicedCount = (clone $projectQuery)
            ->where('status', 'draft')
            ->count();
        $operationallyCompletedCount = (clone $projectQuery)
            ->where('status', 'operationally_completed')
            ->count();

        $totalCollections = (clone $collectionQuery)->sum('amount');
        $todayCollections = (clone $collectionQuery)->whereDate('collection_date', now()->toDateString())->sum('amount');
        $qidExpiryCount = (clone $personQuery)->whereDate('id_expiration_date', '<=', now()->addDays($threshold)->toDateString())->count();
        $qidExpiringThisMonthCount = (clone $personQuery)
            ->whereMonth('id_expiration_date', now()->month)
            ->whereYear('id_expiration_date', now()->year)
            ->count();
        $qidExpiringNextMonthCount = (clone $personQuery)
            ->whereMonth('id_expiration_date', now()->addMonth()->month)
            ->whereYear('id_expiration_date', now()->addMonth()->year)
            ->count();
        $qidExpiredCount = (clone $personQuery)
            ->whereDate('id_expiration_date', '<', now()->toDateString())
            ->count();
        $overdueCollections = (clone $collectionQuery)->whereNotNull('next_due_date')->whereDate('next_due_date', '<', now()->toDateString())->count();
        $dueTodayCollections = (clone $collectionQuery)->whereDate('next_due_date', now()->toDateString())->count();
        $unsettledAmount = Settlement::when($request->user()->hasRole('Collector'), fn ($q) => $q->where('collector_id', $request->user()->id))
            ->whereIn('status', ['draft', 'submitted'])
            ->sum('total_amount');
        $qidOverdueCount = (clone $renewalQuery)->where('status', 'expired')->count();
        $qidFollowUpCount = (clone $renewalQuery)->whereIn('status', ['expiring', 'in_progress'])->count();

        // Passport expiry stats
        $passportQuery = Person::whereNotNull('passport_validity');
        if ($assignedPersonIds) {
            $passportQuery->whereIn('id', $assignedPersonIds);
        }
        $passportExpiringThisMonthCount = (clone $passportQuery)
            ->whereMonth('passport_validity', now()->month)
            ->whereYear('passport_validity', now()->year)
            ->count();
        $passportExpiringNextMonthCount = (clone $passportQuery)
            ->whereMonth('passport_validity', now()->addMonth()->month)
            ->whereYear('passport_validity', now()->addMonth()->year)
            ->count();
        $passportExpiredCount = (clone $passportQuery)
            ->whereDate('passport_validity', '<', now()->toDateString())
            ->count();

        return response()->json([
            'active_projects_count' => $activeProjectCount,
            'active_not_invoiced_projects_count' => $activeNotInvoicedCount,
            'operationally_completed_projects_count' => $operationallyCompletedCount,
            'pending_invoice_total' => round($pendingInvoiceTotal, 2),
            'due_today_invoice_total' => round($dueTodayInvoiceTotal, 2),
            'overdue_invoice_total' => round($overdueInvoiceTotal, 2),
            'total_collections_amount' => round($totalCollections, 2),
            'today_collections' => round($todayCollections, 2),
            'qid_expiry_count' => $qidExpiryCount,
            'qid_expiring_this_month_count' => $qidExpiringThisMonthCount,
            'qid_expiring_next_month_count' => $qidExpiringNextMonthCount,
            'qid_expired_count' => $qidExpiredCount,
            'qid_follow_up_count' => $qidFollowUpCount,
            'qid_overdue_count' => $qidOverdueCount,
            'total_projects' => $projectQuery->count(),
            'overdue_collections_count' => $overdueCollections,
            'due_today_collections_count' => $dueTodayCollections,
            'unsettled_amount' => round($unsettledAmount, 2),
            'renewal_in_progress_count' => $renewalQuery->where('status', 'in_progress')->count(),
            'passport_expiring_this_month_count' => $passportExpiringThisMonthCount,
            'passport_expiring_next_month_count' => $passportExpiringNextMonthCount,
            'passport_expired_count' => $passportExpiredCount,
        ]);
    }

    public function outstandingInvoices(Request $request)
    {
        $assignedPersonIds = $this->assignedPersonIds($request);
        $query = Invoice::with([
            'project:id,project_code,person_id',
            'project.person:id,name,company_id',
            'project.person.company:id,name',
        ])->whereIn('status', ['issued', 'partially_paid']);

        if ($assignedPersonIds) {
            $query->whereHas('project', fn ($q) => $q->whereIn('person_id', $assignedPersonIds));
        }
        if ($request->filled('company_id')) {
            $query->whereHas('project.person', fn ($q) => $q->where('company_id', $request->company_id));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $rows = $query->get()->map(function ($invoice) {
            $days = $invoice->due_date ? max(0, now()->startOfDay()->diffInDays($invoice->due_date, false) * -1) : now()->diffInDays($invoice->issued_at);
            return [
                'id' => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'project_code' => $invoice->project?->project_code,
                'person' => $invoice->project?->person?->name,
                'company' => $invoice->project?->person?->company?->name,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'deduction_amount' => (float) $invoice->deduction_amount,
                'outstanding_amount' => (float) $invoice->outstanding_amount,
                'issued_at' => optional($invoice->issued_at)->toDateString(),
                'due_date' => optional($invoice->due_date)->toDateString(),
                'aging_days' => $days,
            ];
        });

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total_outstanding' => round($rows->sum('outstanding_amount'), 2),
                'count' => $rows->count(),
            ],
        ]);
    }

    public function collectionsSummary(Request $request)
    {
        $assignedPersonIds = $this->assignedPersonIds($request);
        $query = Collection::with(['invoice.project.person.company:id,name', 'collector:id,name']);

        if ($assignedPersonIds) {
            $query->whereHas('invoice.project', fn ($q) => $q->whereIn('person_id', $assignedPersonIds));
        }
        if ($request->filled('collector_id')) {
            $query->where('user_id', $request->collector_id);
        }
        if ($request->filled('project_id')) {
            $query->whereHas('invoice', fn ($q) => $q->where('project_id', $request->project_id));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('collection_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('collection_date', '<=', $request->to_date);
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total_collected' => round($rows->sum('amount'), 2),
                'count' => $rows->count(),
                'cash_total' => round($rows->where('method', 'cash')->sum('amount'), 2),
                'bank_transfer_total' => round($rows->where('method', 'bank_transfer')->sum('amount'), 2),
            ],
        ]);
    }

    public function collectionsFeed(Request $request)
    {
        $assignedPersonIds = $this->assignedPersonIds($request);
        $query = Collection::with([
            'invoice:id,invoice_code,project_id,total_amount,deduction_amount',
            'invoice.collections:id,invoice_id,amount',
            'invoice.project:id,person_id,project_code',
            'invoice.project.person:id,name,company_id',
            'invoice.project.person.company:id,name',
            'collector:id,name',
        ])->latest('collection_date');

        if ($assignedPersonIds) {
            $query->whereHas('invoice.project', fn ($q) => $q->whereIn('person_id', $assignedPersonIds));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('collection_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('collection_date', '<=', $request->to_date);
        }

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    public function deductions(Request $request)
    {
        $query = InvoiceDeduction::with(['invoice:id,invoice_code,project_id', 'invoice.project:id,project_code,person_id', 'invoice.project.person:id,name', 'creator:id,name'])
            ->latest('deducted_at');

        if ($request->filled('from_date')) {
            $query->whereDate('deducted_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('deducted_at', '<=', $request->to_date);
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total_deductions' => round($rows->sum('amount'), 2),
                'count' => $rows->count(),
            ],
        ]);
    }

    public function settlements(Request $request)
    {
        $query = Settlement::with(['collector:id,name', 'verifier:id,name', 'collections.invoice:id,invoice_code']);

        if ($request->user()->hasRole('Collector')) {
            $query->where('collector_id', $request->user()->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->latest()->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'submitted_total' => round($rows->where('status', 'submitted')->sum('total_amount'), 2),
                'settled_total' => round($rows->where('status', 'settled')->sum('total_amount'), 2),
            ],
        ]);
    }

    public function financeSummary(Request $request)
    {
        $query = FinancialEntry::with(['head:id,name,type', 'project:id,project_code']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('financial_head_id')) {
            $query->where('financial_head_id', $request->financial_head_id);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('entry_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('entry_date', '<=', $request->to_date);
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'income_total' => round($rows->where('type', 'income')->sum('amount'), 2),
                'expense_total' => round($rows->where('type', 'expense')->sum('amount'), 2),
            ],
        ]);
    }

    public function qidRenewals(Request $request)
    {
        $query = QidRenewal::with(['person:id,name,phone,qatar_id,company_id', 'person.company:id,name', 'assignee:id,name']);

        if ($request->user()->hasRole('Collector')) {
            $assignedPersonIds = $request->user()->assignedAccounts()->where('is_active', true)->pluck('person_id');
            $query->whereIn('person_id', $assignedPersonIds);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->orderBy('current_expiry_date')->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'expiring' => $rows->where('status', 'expiring')->count(),
                'expired' => $rows->where('status', 'expired')->count(),
                'renewed' => $rows->where('status', 'renewed')->count(),
            ],
        ]);
    }

    public function expensesByCategory(Request $request)
    {
        $query = Expense::query();

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $data = $query->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name,parent_id', 'category.parent:id,name')
            ->get();

        return response()->json([
            'data' => $data,
            'grand_total' => round($data->sum('total'), 2),
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user:id,name');
        return response()->json($query->latest('created_at')->paginate(50));
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $dataset = match ($report) {
            'collections' => ($this->collectionsSummary($request)->getData(true)['data'] ?? []),
            'outstanding' => ($this->outstandingInvoices($request)->getData(true)['data'] ?? []),
            'deductions' => ($this->deductions($request)->getData(true)['data'] ?? []),
            'settlements' => ($this->settlements($request)->getData(true)['data'] ?? []),
            'renewals' => ($this->qidRenewals($request)->getData(true)['data'] ?? []),
            'finance' => ($this->financeSummary($request)->getData(true)['data'] ?? []),
            default => [],
        };

        return response()->streamDownload(function () use ($dataset) {
            $handle = fopen('php://output', 'w');
            if (empty($dataset)) {
                fputcsv($handle, ['No data']);
            } else {
                fputcsv($handle, array_keys((array) $dataset[0]));
                foreach ($dataset as $row) {
                    fputcsv($handle, array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, (array) $row));
                }
            }
            fclose($handle);
        }, $report . '-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
