<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialEntry;
use App\Models\FinancialHead;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function heads(Request $request)
    {
        $query = FinancialHead::with('children');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('type')->orderBy('name')->get());
    }

    public function storeHead(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:financial_heads,id',
        ]);

        return response()->json(FinancialHead::create($validated), 201);
    }

    public function entries(Request $request)
    {
        $query = FinancialEntry::with(['head:id,name,type', 'project:id,project_code', 'person:id,name', 'creator:id,name', 'settlement:id,settlement_code']);

        foreach (['type', 'financial_head_id', 'project_id', 'person_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('from_date')) {
            $query->whereDate('entry_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('entry_date', '<=', $request->to_date);
        }

        return response()->json($query->latest('entry_date')->paginate(20));
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'financial_head_id' => 'required|exists:financial_heads,id',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'project_id' => 'nullable|exists:projects,id',
            'person_id' => 'nullable|exists:persons,id',
            'settlement_id' => 'nullable|exists:settlements,id',
            'document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $entry = FinancialEntry::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($entry->load(['head:id,name,type', 'project:id,project_code', 'person:id,name']), 201);
    }
}
