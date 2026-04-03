<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Invoice;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $query = Collection::with([
            'invoice:id,invoice_code,total_amount,project_id',
            'invoice.project:id,project_code,person_id',
            'invoice.project.person:id,name,id_expiration_date',
            'collector:id,name',
        ]);

        if ($request->user()->hasRole('Collector')) {
            $assignedPersonIds = $request->user()->assignedAccounts()->where('is_active', true)->pluck('person_id');
            $query->whereHas('invoice.project', function ($q) use ($assignedPersonIds) {
                $q->whereIn('person_id', $assignedPersonIds);
            });
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('verified')) {
            $query->where('verified', $request->boolean('verified'));
        }
        if ($request->filled('from_date')) {
            $query->where('collection_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('collection_date', '<=', $request->to_date);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'collection_date' => 'required|date',
            'next_due_date' => 'nullable|date|after_or_equal:collection_date',
            'method' => 'required|in:bank_transfer,cash,mobile_transfer,cheque',
            'notes' => 'nullable|string',
        ]);

        $actualMethod = $validated['method'];
        if (in_array($actualMethod, ['mobile_transfer', 'cheque'])) {
            $validated['method'] = 'bank_transfer';
            $modeString = "[Mode: {$actualMethod}]";
            $validated['notes'] = empty($validated['notes']) ? $modeString : $modeString . " " . $validated['notes'];
        }

        $invoice = Invoice::with('project')->findOrFail($validated['invoice_id']);

        if ($request->user()->hasRole('Collector')) {
            $isAssigned = $request->user()->assignedAccounts()
                ->where('is_active', true)
                ->where('person_id', $invoice->project->person_id)
                ->exists();

            if (!$isAssigned) {
                return response()->json(['message' => 'This invoice is not under your assigned accounts.'], 403);
            }
        }

        if ($validated['amount'] > $invoice->outstanding_amount) {
            return response()->json(['message' => 'Collection amount cannot exceed the invoice outstanding balance.'], 422);
        }

        $isCollector = $request->user()->hasRole('Collector');
        $shouldAutoVerify = !$isCollector || $actualMethod === 'cash';

        $validated['user_id'] = $request->user()->id;
        $validated['settlement_status'] = $isCollector ? 'pending' : 'not_required';
        if ($shouldAutoVerify) {
            $validated['verified'] = true;
            $validated['verified_by'] = $request->user()->id;
            $validated['verified_at'] = now();
        }

        $collection = Collection::create($validated);

        // Update Invoice status based on the new outstanding amount
        $invoice->refresh(); // Retrieve updated computed attributes
        
        $invoiceUpdateData = [];
        if ($invoice->outstanding_amount <= 0) {
            $invoiceUpdateData['status'] = 'paid';
        } else {
            $invoiceUpdateData['status'] = 'partially_paid';
        }

        if (!empty($validated['next_due_date'])) {
            $invoiceUpdateData['due_date'] = $validated['next_due_date'];
        }

        $invoice->update($invoiceUpdateData);

        return response()->json(
            $collection->load('invoice:id,invoice_code'),
            201
        );
    }

    public function show(Collection $collection)
    {
        if (request()->user()->hasRole('Collector')) {
            $assignedPersonIds = request()->user()->assignedAccounts()->where('is_active', true)->pluck('person_id');
            $collection->loadMissing('invoice.project');

            if (!$assignedPersonIds->contains($collection->invoice?->project?->person_id)) {
                return response()->json(['message' => 'This collection is not assigned to you.'], 403);
            }
        }

        return response()->json(
            $collection->load([
                'invoice:id,invoice_code,total_amount',
                'collector:id,name',
                'verifier:id,name',
            ])
        );
    }

    public function verify(Request $request, Collection $collection)
    {
        $collection->update([
            'verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return response()->json($collection->load('verifier:id,name'));
    }
}
