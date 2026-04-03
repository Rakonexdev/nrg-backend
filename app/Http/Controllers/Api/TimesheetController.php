<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProfession;
use App\Models\Timesheet;
use App\Models\TimesheetGroup;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    private function validateTimesheetWindow(ProjectProfession $personnel, string $startDate, string $endDate): ?array
    {
        if ($personnel->status === 'inactive' && $personnel->deactivated_at && $endDate > $personnel->deactivated_at->toDateString()) {
            return ['message' => 'This personnel is deactivated for the selected date range.', 'status' => 422];
        }

        if ($startDate < $personnel->date_of_join->toDateString()) {
            return ['message' => 'Start date cannot be earlier than the personnel join date.', 'status' => 422];
        }

        if ($endDate > now()->toDateString()) {
            return ['message' => 'End date cannot be in the future.', 'status' => 422];
        }

        $latestTimesheet = Timesheet::where('project_profession_id', $personnel->id)
            ->orderByDesc('date_to')
            ->first();

        if ($latestTimesheet && $startDate <= $latestTimesheet->date_to->toDateString()) {
            return ['message' => 'Start date must be later than the previous timesheet end date for this personnel.', 'status' => 422];
        }

        $existingOverlap = Timesheet::where('project_profession_id', $personnel->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date_from', [$startDate, $endDate])
                    ->orWhereBetween('date_to', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('date_from', '<=', $startDate)
                            ->where('date_to', '>=', $endDate);
                    });
            })
            ->exists();

        if ($existingOverlap) {
            return ['message' => 'This personnel already has a timesheet in the selected period.', 'status' => 422];
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = Timesheet::with(['project:id,project_code', 'personnel:id,project_id,profession_name,name', 'group:id,group_code,status']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('timesheet_group_id')) {
            $query->where('timesheet_group_id', $request->timesheet_group_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function groups(Request $request)
    {
        $query = TimesheetGroup::with(['project:id,project_code,type', 'invoice:id,invoice_code', 'timesheets.personnel:id,project_id,profession_name,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'rows' => 'required|array|min:1',
            'rows.*.project_profession_id' => 'required|exists:project_professions,id',
            'rows.*.start_date' => 'required|date',
            'rows.*.end_date' => 'required|date',
            'rows.*.total_hours' => 'required|numeric|min:0.01',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        if ($project->type !== 'variable') {
            return response()->json(['message' => 'Only variable projects can save timesheet groups.'], 422);
        }

        $group = TimesheetGroup::create([
            'group_code' => TimesheetGroup::generateCode(),
            'project_id' => $project->id,
            'status' => 'open',
        ]);

        $created = [];
        foreach ($validated['rows'] as $row) {
            $personnel = ProjectProfession::where('id', $row['project_profession_id'])
                ->where('project_id', $project->id)
                ->firstOrFail();

            $startDate = $row['start_date'];
            $endDate = $row['end_date'];

            $validationError = $this->validateTimesheetWindow($personnel, $startDate, $endDate);
            if ($validationError) {
                return response()->json(['message' => $validationError['message']], $validationError['status']);
            }

            $totalPrice = round($personnel->hourly_rate * $row['total_hours'], 2);

            $created[] = Timesheet::create([
                'timesheet_group_id' => $group->id,
                'project_id' => $project->id,
                'project_profession_id' => $personnel->id,
                'person_id' => $project->person_id,
                'date_logged' => now(),
                'profession_name' => $personnel->profession_name,
                'rate_per_hour' => $personnel->hourly_rate,
                'total_hours' => $row['total_hours'],
                'date_from' => $startDate,
                'date_to' => $endDate,
                'total_price' => $totalPrice,
                'created_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'group' => $group->load(['timesheets.personnel']),
            'rows' => $created,
        ], 201);
    }

    public function show(Timesheet $timesheet)
    {
        return response()->json($timesheet->load(['project:id,project_code', 'creator:id,name', 'personnel', 'group']));
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->group && $timesheet->group->status === 'invoiced') {
            return response()->json(['message' => 'Invoiced timesheets are locked and cannot be edited.'], 422);
        }

        $validated = $request->validate([
            'date_logged' => 'nullable|date',
            'profession_name' => 'sometimes|string',
            'rate_per_hour' => 'sometimes|numeric|min:0',
            'total_hours' => 'sometimes|numeric|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'total_price' => 'nullable|numeric|min:0',
        ]);

        $startDate = $validated['date_from'] ?? $timesheet->date_from->toDateString();
        $endDate = $validated['date_to'] ?? $timesheet->date_to->toDateString();
        $personnel = $timesheet->personnel;

        $validationError = $this->validateTimesheetWindow($personnel, $startDate, $endDate);
        if ($validationError) {
            $conflictExists = Timesheet::where('project_profession_id', $personnel->id)
                ->where('id', '!=', $timesheet->id)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date_from', [$startDate, $endDate])
                        ->orWhereBetween('date_to', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('date_from', '<=', $startDate)
                                ->where('date_to', '>=', $endDate);
                        });
                })
                ->exists();

            if ($conflictExists || $validationError['message'] !== 'Start date must be later than the previous timesheet end date for this personnel.') {
                return response()->json(['message' => $validationError['message']], $validationError['status']);
            }
        }

        if (isset($validated['total_hours']) && !isset($validated['total_price'])) {
            $validated['total_price'] = round(($validated['rate_per_hour'] ?? $timesheet->rate_per_hour) * $validated['total_hours'], 2);
        }

        $timesheet->update($validated);

        return response()->json($timesheet);
    }

    public function destroy(Timesheet $timesheet)
    {
        if ($timesheet->group && $timesheet->group->status === 'invoiced') {
            return response()->json(['message' => 'Invoiced timesheets are locked and cannot be deleted.'], 422);
        }

        $timesheet->delete();

        return response()->json(['message' => 'Timesheet deleted']);
    }
}
