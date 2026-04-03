<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\TimesheetGroup;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('project:id,project_code,person_id', 'project.person:id,name', 'timesheetGroup');

        if ($request->user()->hasRole('Collector')) {
            $assignedPersonIds = $request->user()
                ->assignedAccounts()
                ->where('is_active', true)
                ->pluck('person_id');

            $query->whereHas('project', function ($projectQuery) use ($assignedPersonIds) {
                $projectQuery->whereIn('person_id', $assignedPersonIds);
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'timesheet_group_id' => 'nullable|exists:timesheet_groups,id',
            'reference_number' => 'nullable|string|max:100',
            'total_amount' => 'nullable|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
            'file_url' => 'nullable|string',
            'evidence_url' => 'nullable|string',
            'status' => 'nullable|in:draft,issued,partially_paid,paid,cancelled',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $validated['invoice_code'] = Invoice::generateCode();
        $validated['issued_at'] = now();
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'issued';
        $validated['discount_amount'] = $validated['discount_amount'] ?? 0;
        $validated['deduction_amount'] = 0;

        if ($project->type === 'fixed') {
            $existingInvoiceCount = $project->invoices()->count();
            if ($existingInvoiceCount > 0) {
                return response()->json(['message' => 'Fixed projects can only have one invoice in V1.0.'], 422);
            }

            $validated['total_amount'] = $project->fixed_total_amount;
            if (!$validated['reference_number']) {
                return response()->json(['message' => 'Manual reference number is required for fixed project invoices.'], 422);
            }
        } else {
            if (empty($validated['timesheet_group_id'])) {
                return response()->json(['message' => 'Variable project invoices must be created from a saved timesheet group.'], 422);
            }

            $group = TimesheetGroup::with('timesheets')->findOrFail($validated['timesheet_group_id']);
            if ($group->project_id !== $project->id) {
                return response()->json(['message' => 'Selected timesheet group does not belong to this project.'], 422);
            }
            if ($group->status === 'invoiced' || $group->invoice_id) {
                return response()->json(['message' => 'This timesheet group has already been invoiced.'], 422);
            }

            $validated['total_amount'] = $group->timesheets->sum('total_price');
        }

        $validated['due_date'] = now()
            ->addDays((int) ($project->payment_credit_days ?? Setting::getValue('finance.default_credit_days', 0)))
            ->toDateString();

        $invoice = Invoice::create($validated);

        if (!empty($validated['timesheet_group_id'])) {
            TimesheetGroup::where('id', $validated['timesheet_group_id'])->update([
                'invoice_id' => $invoice->id,
                'status' => 'invoiced',
                'closed_at' => now(),
            ]);
        }

        if (!in_array($project->status, ['operationally_completed', 'financially_closed', 'cancelled'], true)) {
            $project->update(['status' => 'invoiced']);
        }

        return response()->json($invoice->load(['project:id,project_code', 'timesheetGroup.timesheets']), 201);
    }

    public function show(Invoice $invoice)
    {
        if (request()->user()->hasRole('Collector')) {
            $assignedPersonIds = request()->user()
                ->assignedAccounts()
                ->where('is_active', true)
                ->pluck('person_id');

            if (!$assignedPersonIds->contains($invoice->project?->person_id)) {
                return response()->json(['message' => 'This invoice is not assigned to you.'], 403);
            }
        }

        return response()->json(
            $invoice->load([
                'project:id,project_code,person_id',
                'project.person:id,name',
                'timesheetGroup.timesheets.personnel',
                'collections',
                'collections.collector:id,name',
                'creator:id,name',
            ])
        );
    }
}
