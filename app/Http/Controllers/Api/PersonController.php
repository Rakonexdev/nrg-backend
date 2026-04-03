<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonFile;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function __construct(private MediaStorageService $mediaStorage)
    {
    }

    public function index(Request $request)
    {
        $query = Person::with([
            'company:id,name',
            'activeCollectorAssignment:id,person_id,collector_id,assigned_at,is_active',
            'activeCollectorAssignment.collector:id,name',
            'files',
        ]);

        if ($request->user()->hasRole('Collector')) {
            $query->whereHas('activeCollectorAssignment', function ($assignment) use ($request) {
                $assignment->where('collector_id', $request->user()->id)
                    ->where('is_active', true);
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('qatar_id', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'qatar_id' => 'required|string',
            'id_expiration_date' => 'required|date',
            'id_photo_url' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'date_of_birth' => 'nullable|date',
            'nationality' => 'nullable|string|max:100',
            'passport_number' => 'nullable|string|max:50',
            'passport_validity' => 'nullable|date',
        ]);

        $existing = Person::query()
            ->where('phone', $validated['phone'])
            ->orWhere('qatar_id', $validated['qatar_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A person with the same phone or Qatar ID already exists.',
                'existing_person' => $existing->load('company:id,name'),
            ], 409);
        }

        $person = Person::create($validated);

        return response()->json($person->load(['company:id,name', 'files']), 201);
    }

    public function show(Person $person)
    {
        if (request()->user()->hasRole('Collector')) {
            $assigned = $person->collectorAssignments()
                ->where('collector_id', request()->user()->id)
                ->where('is_active', true)
                ->exists();

            if (!$assigned) {
                return response()->json(['message' => 'This customer is not assigned to you.'], 403);
            }
        }

        return response()->json(
            $person->load([
                'company:id,name',
                'projects',
                'projects.invoices',
                'activeCollectorAssignment:id,person_id,collector_id,assigned_at,is_active',
                'activeCollectorAssignment.collector:id,name',
                'files',
            ])
        );
    }

    public function update(Request $request, Person $person)
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
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:persons,phone,' . $person->id,
            'qatar_id' => 'sometimes|string|unique:persons,qatar_id,' . $person->id,
            'id_expiration_date' => 'sometimes|date',
            'id_photo_url' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'date_of_birth' => 'nullable|date',
            'nationality' => 'nullable|string|max:100',
            'passport_number' => 'nullable|string|max:50',
            'passport_validity' => 'nullable|date',
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
            'files',
        ]));
    }

    public function uploadFile(Request $request, Person $person)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
            'category' => 'required|in:id_document,passport,contract,other,document',
        ]);

        $stored = $this->mediaStorage->store($request->file('file'), 'person-files');

        $personFile = $person->files()->create([
            'category' => $request->category,
            'file_url' => $stored['url'] ?? $stored['path'] ?? $stored,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($personFile, 201);
    }

    public function deleteFile(Person $person, PersonFile $file)
    {
        $file->delete();

        return response()->json(['message' => 'File removed']);
    }
}
