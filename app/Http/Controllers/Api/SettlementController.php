<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Settlement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $query = Settlement::with(['collector:id,name', 'collections.invoice:id,invoice_code', 'verifier:id,name'])
            ->latest('created_at');

        if ($request->user()->hasRole('Collector')) {
            $query->where('collector_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'data' => $query->paginate(20),
            'settlement_interval_days' => (int) Setting::getValue('settlement.interval_days', 7),
            'collectors' => User::role('Collector')->where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function availableCollections(Request $request)
    {
        $validated = $request->validate([
            'collector_id' => 'nullable|exists:users,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        $collectorId = $request->user()->hasRole('Collector')
            ? $request->user()->id
            : ($validated['collector_id'] ?? null);

        $query = Collection::with(['invoice:id,invoice_code,project_id', 'invoice.project:id,person_id', 'invoice.project.person:id,name'])
            ->where('settlement_status', 'pending');

        if ($collectorId) {
            $query->where('user_id', $collectorId);
        }
        if (!empty($validated['from_date'])) {
            $query->whereDate('collection_date', '>=', $validated['from_date']);
        }
        if (!empty($validated['to_date'])) {
            $query->whereDate('collection_date', '<=', $validated['to_date']);
        }

        return response()->json($query->orderBy('collection_date')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'collector_id' => 'nullable|exists:users,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'collection_ids' => 'required|array|min:1',
            'collection_ids.*' => 'exists:collections,id',
            'notes' => 'nullable|string',
        ]);

        $collectorId = $request->user()->hasRole('Collector')
            ? $request->user()->id
            : ($validated['collector_id'] ?? null);

        if (!$collectorId) {
            return response()->json(['message' => 'Collector is required.'], 422);
        }

        $collections = Collection::whereIn('id', $validated['collection_ids'])
            ->where('user_id', $collectorId)
            ->where('settlement_status', 'pending')
            ->get();

        if ($collections->count() !== count($validated['collection_ids'])) {
            return response()->json(['message' => 'One or more collections are not available for settlement.'], 422);
        }

        $settlement = Settlement::create([
            'settlement_code' => Settlement::generateCode(),
            'collector_id' => $collectorId,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'total_amount' => round($collections->sum('amount'), 2),
            'status' => $request->user()->hasRole('Collector') ? 'submitted' : 'draft',
            'submitted_at' => $request->user()->hasRole('Collector') ? now() : null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $settlement->collections()->sync($collections->pluck('id'));

        Collection::whereIn('id', $collections->pluck('id'))->update([
            'settlement_status' => $request->user()->hasRole('Collector') ? 'submitted' : 'draft',
        ]);

        return response()->json($settlement->load(['collector:id,name', 'collections.invoice:id,invoice_code']), 201);
    }

    public function updateStatus(Request $request, Settlement $settlement)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,settled,cancelled',
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'submitted') {
            $updates['submitted_at'] = now();
        }
        if ($validated['status'] === 'settled') {
            $updates['settled_at'] = now();
            $updates['verified_by'] = $request->user()->id;
        }

        $settlement->update($updates);

        $collectionStatus = match ($validated['status']) {
            'settled' => 'settled',
            'cancelled' => 'pending',
            'submitted' => 'submitted',
            default => 'draft',
        };

        Collection::whereIn('id', $settlement->collections()->pluck('collections.id'))->update([
            'settlement_status' => $collectionStatus,
        ]);

        return response()->json($settlement->load(['collector:id,name', 'verifier:id,name']));
    }
}
