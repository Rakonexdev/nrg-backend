<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\QidRenewal;
use App\Models\Setting;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class QidRenewalController extends Controller
{
    public function __construct(private MediaStorageService $mediaStorage)
    {
    }

    private function syncRenewals(): void
    {
        $threshold = (int) Setting::getValue('qid.threshold_days', 30);

        Person::whereNotNull('id_expiration_date')
            ->whereDate('id_expiration_date', '<=', now()->addDays($threshold)->toDateString())
            ->chunkById(100, function ($persons) {
                foreach ($persons as $person) {
                    QidRenewal::updateOrCreate(
                        ['person_id' => $person->id],
                        [
                            'current_expiry_date' => $person->id_expiration_date,
                            'status' => $person->id_expiration_date < now()->toDateString() ? 'expired' : 'expiring',
                        ]
                    );
                }
            });
    }

    public function index(Request $request)
    {
        $this->syncRenewals();

        $query = QidRenewal::with(['person:id,name,phone,qatar_id,id_expiration_date,company_id', 'person.company:id,name', 'assignee:id,name']);

        if ($request->user()->hasRole('Collector')) {
            $assignedPersonIds = $request->user()->assignedAccounts()->where('is_active', true)->pluck('person_id');
            $query->whereIn('person_id', $assignedPersonIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        return response()->json([
            'data' => $query->orderBy('current_expiry_date')->paginate(20),
            'staff' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'threshold_days' => (int) Setting::getValue('qid.threshold_days', 30),
        ]);
    }

    public function update(Request $request, QidRenewal $qidRenewal)
    {
        if ($request->user()->hasRole('Collector')) {
            $assigned = $request->user()->assignedAccounts()
                ->where('is_active', true)
                ->where('person_id', $qidRenewal->person_id)
                ->exists();

            if (!$assigned) {
                return response()->json(['message' => 'This renewal record is not assigned to your customer list.'], 403);
            }
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:expiring,expired,in_progress,renewed',
            'current_expiry_date' => 'required|date',
            'renewed_on' => 'nullable|date',
            'latest_document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $oldDocument = $qidRenewal->latest_document_url;
        $qidRenewal->update($validated);
        if (!empty($validated['latest_document_url']) && $validated['latest_document_url'] !== $oldDocument) {
            $this->mediaStorage->archiveIfReplaced($oldDocument);
        }

        if ($validated['status'] === 'renewed') {
            $qidRenewal->person()->update([
                'id_expiration_date' => $validated['current_expiry_date'],
                'id_photo_url' => $validated['latest_document_url'] ?? $qidRenewal->person->id_photo_url,
            ]);
        }

        return response()->json($qidRenewal->load(['person:id,name,id_expiration_date', 'assignee:id,name']));
    }
}
