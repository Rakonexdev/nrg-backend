<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectContact;
use App\Models\ProjectFile;
use App\Models\ProjectProfession;
use App\Services\MediaStorageService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private function appendWorkflowState(Project $project): Project
    {
        $project->loadMissing([
            'quotations:id,project_id,document_url,quoted_amount,status',
            'invoices:id,project_id,timesheet_group_id',
            'timesheets:id,project_id,timesheet_group_id',
            'professions:id,project_id,profession_name,name,hourly_rate,date_of_join,status',
        ]);

        $hasQuoteStage = $project->quotations->count() > 0
            || ($project->type === 'variable' && $project->professions->where('status', 'active')->count() > 0);

        $hasInvoiceStage = $project->type === 'fixed'
            ? $project->invoices->count() > 0
            : $project->invoices->whereNotNull('timesheet_group_id')->count() > 0;

        $canMarkCompleted = $project->invoices->count() > 0;

        $project->setAttribute('stage_flags', [
            'quoted' => $hasQuoteStage,
            'invoiced' => $hasInvoiceStage,
            'project_completed' => $canMarkCompleted,
            'can_withdraw' => in_array($project->status, ['draft', 'quoted'], true),
            'is_withdrawn' => $project->status === 'cancelled',
        ]);

        return $project;
    }

    public function index(Request $request)
    {
        $query = Project::with([
            'person:id,name,company_id',
            'person.company:id,name',
            'person.activeCollectorAssignment:id,person_id,collector_id,is_active',
            'person.activeCollectorAssignment.collector:id,name',
            'collector:id,name',
            'contacts',
            'professions',
            'quotations:id,project_id,document_url,quoted_amount,status',
            'invoices:id,project_id,timesheet_group_id',
            'timesheets:id,project_id,timesheet_group_id',
            'files',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('person_id')) {
            $query->where('person_id', $request->person_id);
        }
        if ($request->filled('search')) {
            $query->where('project_code', 'like', "%{$request->search}%");
        }

        $limit = $request->input('limit') === 'all' ? max(1, $query->count()) : $request->input('limit', 20);
        $paginated = $query->latest()->paginate($limit);
        $paginated->setCollection(
            $paginated->getCollection()->map(fn ($project) => $this->appendWorkflowState($project))
        );

        return response()->json($paginated);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'required|exists:persons,id',
            'collector_id' => 'nullable|exists:users,id',
            'type' => 'required|in:fixed,variable',
            'project_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:50',
            'status' => ['nullable', Rule::in(['draft', 'quoted', 'invoiced', 'active', 'operationally_completed', 'financially_closed', 'cancelled'])],
            'lpo_url' => 'nullable|string',
            'fixed_total_amount' => 'nullable|numeric|min:0',
            'fixed_description' => 'nullable|string',
            'bill_frequency' => 'nullable|string|max:50',
            'payment_credit_days' => 'nullable|integer|min:0',
            'professions' => 'nullable|array',
            'professions.*.profession_name' => 'required_with:professions|string',
            'professions.*.name' => 'nullable|string',
            'professions.*.hourly_rate' => 'required_with:professions|numeric|min:0',
            'professions.*.date_of_join' => 'nullable|date',
            'professions.*.no_of_persons' => 'nullable|integer|min:1',
            'professions.*.status' => 'nullable|in:active,inactive',
            'professions.*.deactivated_at' => 'nullable|date',
        ]);

        // Check collector project limit (max 2 projects per collector)
        if (!empty($validated['collector_id'])) {
            $collectorProjectCount = Project::where('collector_id', $validated['collector_id'])
                ->whereNotIn('status', ['cancelled', 'financially_closed'])
                ->count();

            if ($collectorProjectCount >= 2) {
                return response()->json([
                    'message' => 'This collector is already assigned to 2 projects. A collector cannot be assigned to more than 2 projects.',
                ], 422);
            }
        }

        $validated['project_code'] = Project::generateCode();
        $validated['status'] = $validated['status'] ?? 'draft';

        $project = Project::create($validated);
        ProjectContact::create([
            'project_id' => $project->id,
            'person_id' => $project->person_id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        if ($request->type === 'variable' && $request->has('professions')) {
            foreach ($request->input('professions') as $prof) {
                $project->professions()->create([
                    ...$prof,
                    'status' => $prof['status'] ?? 'active',
                    'date_of_join' => $prof['date_of_join'] ?? now()->toDateString(),
                ]);
            }
        }

        return response()->json(
            $project->load(['person:id,name', 'professions']),
            201
        );
    }

    public function show(Project $project)
    {
        return response()->json(
            $this->appendWorkflowState($project->load([
                'person:id,name,phone,company_id',
                'person.company:id,name',
                'person.activeCollectorAssignment:id,person_id,collector_id,is_active',
                'person.activeCollectorAssignment.collector:id,name',
                'collector:id,name',
                'contacts.person:id,name',
                'professions',
                'quotations',
                'timesheets',
                'files',
                'invoices',
                'invoices.collections',
            ]))
        );
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'person_id' => 'sometimes|exists:persons,id',
            'collector_id' => 'nullable|exists:users,id',
            'reference_number' => 'nullable|string|max:50',
            'project_date' => 'nullable|date',
            'type' => 'sometimes|in:fixed,variable',
            'status' => ['sometimes', Rule::in(['draft', 'quoted', 'invoiced', 'active', 'operationally_completed', 'financially_closed', 'cancelled'])],
            'lpo_url' => 'nullable|string',
            'fixed_total_amount' => 'nullable|numeric|min:0',
            'fixed_description' => 'nullable|string',
            'bill_frequency' => 'nullable|string|max:50',
            'payment_credit_days' => 'nullable|integer|min:0',
            'professions' => 'nullable|array',
            'professions.*.profession_name' => 'required_with:professions|string',
            'professions.*.name' => 'nullable|string',
            'professions.*.hourly_rate' => 'required_with:professions|numeric|min:0',
            'professions.*.date_of_join' => 'nullable|date',
            'professions.*.no_of_persons' => 'nullable|integer|min:1',
            'professions.*.status' => 'nullable|in:active,inactive',
            'professions.*.deactivated_at' => 'nullable|date',
        ]);

        // Check collector project limit (max 2 projects per collector)
        if (!empty($validated['collector_id']) && $validated['collector_id'] != $project->collector_id) {
            $collectorProjectCount = Project::where('collector_id', $validated['collector_id'])
                ->whereNotIn('status', ['cancelled', 'financially_closed'])
                ->where('id', '!=', $project->id)
                ->count();

            if ($collectorProjectCount >= 2) {
                return response()->json([
                    'message' => 'This collector is already assigned to 2 projects. A collector cannot be assigned to more than 2 projects.',
                ], 422);
            }
        }

        $oldPersonId = $project->person_id;
        $project->update($validated);

        if (isset($validated['person_id']) && $validated['person_id'] !== $oldPersonId) {
            ProjectContact::where('project_id', $project->id)->where('is_active', true)->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

            ProjectContact::create([
                'project_id' => $project->id,
                'person_id' => $validated['person_id'],
                'assigned_at' => now(),
                'is_active' => true,
            ]);
        }

        if (in_array($request->type, ['variable']) || $project->type === 'variable') {
            if ($request->has('professions')) {
                foreach ($request->input('professions') as $idx => $prof) {
                    if (!empty($prof['id'])) {
                        $projectProfession = $project->professions()->whereKey($prof['id'])->first();
                        if ($projectProfession) {
                            $projectProfession->update($prof);
                        }
                        continue;
                    }

                    $project->professions()->create([
                        ...$prof,
                        'status' => $prof['status'] ?? 'active',
                        'date_of_join' => $prof['date_of_join'] ?? now()->toDateString(),
                    ]);
                }
            }
        } else {
            $project->professions()->delete();
        }

        return response()->json($project->load(['person:id,name', 'professions']));
    }

    public function updateStatus(Request $request, Project $project)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'quoted', 'invoiced', 'active', 'operationally_completed', 'financially_closed', 'cancelled', 'archived'])],
            'withdrawal_reason' => 'nullable|string',
        ]);

        $project = $this->appendWorkflowState($project);
        $flags = $project->stage_flags;
        unset($project->stage_flags);

        if ($validated['status'] === 'operationally_completed' && !$flags['project_completed']) {
            return response()->json(['message' => 'This project is not ready to be marked completed yet.'], 422);
        }

        if ($validated['status'] === 'financially_closed' && $project->status !== 'operationally_completed') {
            return response()->json(['message' => 'Only operationally completed projects can be financially closed.'], 422);
        }

        if ($validated['status'] === 'cancelled' && !in_array($project->status, ['draft', 'quoted'], true)) {
            return response()->json(['message' => 'Only draft or quoted projects can be withdrawn.'], 422);
        }

        if ($validated['status'] === 'draft' && $project->status !== 'cancelled') {
            return response()->json(['message' => 'Only withdrawn projects can be reverted to draft.'], 422);
        }

        if ($validated['status'] === 'archived' && $project->status !== 'cancelled') {
            return response()->json(['message' => 'Only withdrawn projects can be archived.'], 422);
        }

        if ($validated['status'] === 'operationally_completed') {
            $validated['operationally_completed_at'] = Carbon::today();
        }

        if ($validated['status'] === 'financially_closed') {
            $validated['financially_closed_at'] = Carbon::today();
        }

        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        if ($project->status !== 'draft') {
            return response()->json(['message' => 'Only draft projects can be deleted.'], 422);
        }

        if ($project->quotations()->exists() || $project->invoices()->exists() || $project->timesheets()->exists()) {
            return response()->json(['message' => 'This draft project already has activity and cannot be deleted. Withdraw it instead.'], 422);
        }

        $project->professions()->delete();
        $project->contacts()->delete();
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    // --- Profession sub-resource ---

    public function storeProfession(Request $request, Project $project)
    {
        $validated = $request->validate([
            'profession_name' => 'required|string',
            'name' => 'nullable|string',
            'hourly_rate' => 'required|numeric|min:0',
            'date_of_join' => 'nullable|date',
            'no_of_persons' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ]);

        $profession = $project->professions()->create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'date_of_join' => $validated['date_of_join'] ?? now()->toDateString(),
        ]);

        return response()->json($profession, 201);
    }

    public function updateProfession(Request $request, Project $project, ProjectProfession $profession)
    {
        $validated = $request->validate([
            'profession_name' => 'sometimes|string',
            'name' => 'sometimes|nullable|string',
            'hourly_rate' => 'sometimes|numeric|min:0',
            'date_of_join' => 'sometimes|nullable|date',
            'no_of_persons' => 'sometimes|nullable|integer|min:1',
            'status' => 'sometimes|in:active,inactive',
            'deactivated_at' => 'nullable|date',
        ]);

        if (($validated['status'] ?? null) === 'inactive') {
            $pendingTimesheets = $profession->timesheets()->whereNull('timesheet_group_id')->count();
            if ($pendingTimesheets > 0) {
                return response()->json([
                    'message' => 'Pending timesheets exist for this personnel. Please generate the timesheet group invoice before deactivating.',
                ], 422);
            }
            $validated['deactivated_at'] = now();
        }

        $profession->update($validated);

        return response()->json($profession);
    }

    public function destroyProfession(Project $project, ProjectProfession $profession)
    {
        $profession->delete();

        return response()->json(['message' => 'Profession removed']);
    }

    public function uploadFile(Request $request, Project $project, MediaStorageService $mediaStorage)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
            'category' => 'required|in:lpo,quotation,agreement,other',
        ]);

        $stored = $mediaStorage->store($request->file('file'), 'project-files');

        $projectFile = $project->files()->create([
            'category' => $request->category,
            'file_url' => $stored['url'] ?? $stored['path'] ?? $stored,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($projectFile, 201);
    }

    public function deleteFile(Project $project, ProjectFile $file)
    {
        $file->delete();

        return response()->json(['message' => 'File removed']);
    }
}
