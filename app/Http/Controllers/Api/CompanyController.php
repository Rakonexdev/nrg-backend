<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::withCount('persons');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
        ]);

        // Similarity check: find close matches
        $similar = Company::where('name', 'like', '%' . trim($validated['name']) . '%')->get();

        $company = Company::create($validated);

        return response()->json([
            'company' => $company,
            'similar_existing' => $similar->count() > 0 ? $similar->pluck('name') : null,
        ], 201);
    }

    public function show(Company $company)
    {
        return response()->json(
            $company->load(['persons', 'persons.projects'])
        );
    }
}
