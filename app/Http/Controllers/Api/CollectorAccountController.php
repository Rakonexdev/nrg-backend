<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectorAssignment;
use App\Models\Person;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class CollectorAccountController extends Controller
{
    public function __construct(private MediaStorageService $mediaStorage)
    {
    }

    public function collectors()
    {
        $collectors = User::role('Collector')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($collectors);
    }

    public function storeCollector(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $email = strtolower(str_replace(' ', '.', trim($validated['name']))) . '.collector@ggcs.local';
        $counter = 1;
        $baseEmail = $email;
        while (User::where('email', $email)->exists()) {
            $email = str_replace('@', $counter . '@', $baseEmail);
            $counter++;
        }

        $collector = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => bcrypt('collector@123'),
            'status' => 'active',
        ]);

        $collector->assignRole('Collector');

        return response()->json($collector->only(['id', 'name', 'email']), 201);
    }

    public function index(Request $request)
    {
        $query = Person::with([
            'company:id,name',
            'activeCollectorAssignment:id,person_id,collector_id,assigned_at,is_active',
            'activeCollectorAssignment.collector:id,name',
        ]);

        if ($request->user()->hasRole('Collector')) {
            $query->whereHas('activeCollectorAssignment', function ($assignment) use ($request) {
                $assignment->where('collector_id', $request->user()->id)
                    ->where('is_active', true);
            });
        }

        if ($request->boolean('expiring_only')) {
            $query->whereDate('id_expiration_date', '<=', now()->addDays(30)->toDateString());
        }

        if ($request->filled('search')) {
            $query->where(function ($personQuery) use ($request) {
                $personQuery->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                    ->orWhere('qatar_id', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function assign(Request $request, Person $person)
    {
        $validated = $request->validate([
            'collector_id' => 'required|exists:users,id',
        ]);

        $collector = User::role('Collector')->findOrFail($validated['collector_id']);

        // Check if same collector is already active for this person
        $alreadyAssigned = CollectorAssignment::where('person_id', $person->id)
            ->where('collector_id', $collector->id)
            ->where('is_active', true)
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'message' => 'This collector is already assigned to this customer.',
            ], 422);
        }

        // Check collector customer limit (max 2 customers per collector)
        $collectorCustomerCount = CollectorAssignment::where('collector_id', $collector->id)
            ->where('person_id', '!=', $person->id)
            ->where('is_active', true)
            ->count();

        if ($collectorCustomerCount >= 2) {
            return response()->json([
                'message' => 'This collector is already assigned to 2 customers. A collector cannot be assigned to more than 2 customers.',
            ], 422);
        }

        CollectorAssignment::where('person_id', $person->id)
            ->where('is_active', true)
            ->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        $assignment = CollectorAssignment::create([
            'person_id' => $person->id,
            'collector_id' => $collector->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        return response()->json($assignment->load('collector:id,name'));
    }

    public function unassign(Request $request, Person $person)
    {
        $updated = CollectorAssignment::where('person_id', $person->id)
            ->where('is_active', true)
            ->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        return response()->json([
            'message' => $updated ? 'Collector unassigned.' : 'No active collector assignment found.',
        ]);
    }

    public function updateIdentity(Request $request, Person $person)
    {
        if ($request->user()->hasRole('Collector')) {
            $assigned = $person->collectorAssignments()
                ->where('collector_id', $request->user()->id)
                ->where('is_active', true)
                ->exists();

            if (!$assigned) {
                return response()->json(['message' => 'This customer is not assigned to you.'], 403);
            }
        }

        $validated = $request->validate([
            'id_expiration_date' => 'required|date',
            'id_photo_url' => 'nullable|string',
        ]);

        $oldDocument = $person->id_photo_url;
        $person->update($validated);
        if (!empty($validated['id_photo_url']) && $validated['id_photo_url'] !== $oldDocument) {
            $this->mediaStorage->archiveIfReplaced($oldDocument);
        }

        return response()->json($person->load([
            'company:id,name',
            'activeCollectorAssignment:id,person_id,collector_id,assigned_at,is_active',
            'activeCollectorAssignment.collector:id,name',
        ]));
    }
}
