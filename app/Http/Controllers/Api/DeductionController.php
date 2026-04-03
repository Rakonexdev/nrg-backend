<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceDeduction;
use Illuminate\Http\Request;

class DeductionController extends Controller
{
    public function index(Request $request)
    {
        $query = InvoiceDeduction::with([
            'invoice:id,invoice_code,project_id,total_amount,deduction_amount',
            'invoice.project:id,project_code,person_id',
            'invoice.project.person:id,name',
            'creator:id,name',
        ])->latest('deducted_at');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'deducted_at' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        if ($validated['amount'] > $invoice->outstanding_amount) {
            return response()->json(['message' => 'Deduction cannot exceed the current outstanding amount.'], 422);
        }

        $deduction = InvoiceDeduction::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $invoice->update([
            'deduction_amount' => round($invoice->deductions()->sum('amount'), 2),
        ]);

        return response()->json($deduction->load('invoice:id,invoice_code', 'creator:id,name'), 201);
    }
}
