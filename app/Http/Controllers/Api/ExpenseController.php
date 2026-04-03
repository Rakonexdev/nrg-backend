<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    // --- Categories ---

    public function categories(Request $request)
    {
        $query = ExpenseCategory::heads()->with('children');

        return response()->json($query->get());
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
        ]);

        $category = ExpenseCategory::create($validated);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    // --- Expenses ---

    public function index(Request $request)
    {
        $query = Expense::with(['category:id,name,parent_id', 'category.parent:id,name', 'creator:id,name']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = $request->user()->id;

        $expense = Expense::create($validated);

        return response()->json($expense->load('category:id,name'), 201);
    }

    public function show(Expense $expense)
    {
        return response()->json(
            $expense->load(['category:id,name,parent_id', 'category.parent:id,name', 'creator:id,name'])
        );
    }
}
