<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationFile;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    private MediaStorageService $mediaStorage;

    public function __construct(MediaStorageService $mediaStorage)
    {
        $this->mediaStorage = $mediaStorage;
    }

    public function index(Request $request)
    {
        $query = Quotation::with([
            'project:id,project_code,person_id,type,status,fixed_total_amount,fixed_description',
            'project.person:id,name',
            'project.professions:id,project_id,profession_name,name,hourly_rate,date_of_join,status',
            'creator:id,name',
            'files',
        ])->latest('quoted_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'quoted_amount' => 'nullable|numeric|min:0.01',
            'scope_summary' => 'nullable|string',
            'manual_reference_number' => 'nullable|string|max:100',
            'document_url' => 'nullable|string',
            'status' => 'nullable|in:draft,uploaded,accepted,cancelled',
            'quoted_at' => 'nullable|date',
        ]);

        $project = Project::with('professions')->findOrFail($validated['project_id']);

        if ($project->type === 'fixed' && empty($validated['quoted_amount'])) {
            $validated['quoted_amount'] = $project->fixed_total_amount;
        }

        if ($project->type === 'variable' && empty($validated['scope_summary'])) {
            $validated['scope_summary'] = $project->professions
                ->map(fn ($row) => trim($row->profession_name . ' | ' . ($row->name ?: 'Open') . ' | QAR ' . $row->hourly_rate . '/hr'))
                ->implode("\n");
        }

        $quotation = Quotation::create([
            ...$validated,
            'quotation_code' => Quotation::generateCode(),
            'quoted_at' => $validated['quoted_at'] ?? now()->toDateString(),
            'status' => $validated['status'] ?? 'uploaded',
            'created_by' => $request->user()->id,
        ]);

        if (in_array($project->status, ['draft', 'quoted'], true)) {
            $project->update(['status' => 'quoted']);
        }

        return response()->json($quotation->load([
            'project:id,project_code,person_id,type,status',
            'project.person:id,name',
            'project.professions:id,project_id,profession_name,name,hourly_rate',
            'creator:id,name',
            'files',
        ]), 201);
    }

    public function show(Quotation $quotation)
    {
        return response()->json($quotation->load([
            'project:id,project_code,person_id,type,status,fixed_total_amount,fixed_description',
            'project.person:id,name',
            'project.professions:id,project_id,profession_name,name,hourly_rate,date_of_join,status',
            'creator:id,name',
            'files',
        ]));
    }

    public function uploadFile(Request $request, Quotation $quotation)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $stored = $this->mediaStorage->store($request->file('file'), 'quotation-files');

        $quotationFile = $quotation->files()->create([
            'file_url' => $stored['url'] ?? $stored['path'] ?? $stored,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($quotationFile, 201);
    }

    public function deleteFile(Quotation $quotation, QuotationFile $file)
    {
        $file->delete();

        return response()->json(['message' => 'File removed']);
    }

    public function destroy(Quotation $quotation)
    {
        $projectId = $quotation->project_id;

        $quotation->files()->delete();
        $quotation->delete();

        // If no quotations remain for this project, revert status to draft
        $remaining = Quotation::where('project_id', $projectId)->count();
        if ($remaining === 0) {
            $project = Project::find($projectId);
            if ($project && $project->status === 'quoted') {
                $project->update(['status' => 'draft']);
            }
        }

        return response()->json(['message' => 'Quotation deleted']);
    }
}
