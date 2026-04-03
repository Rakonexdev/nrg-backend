<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Person;
use Illuminate\Http\Request;

class MobileCollectorController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('Collector')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $assignedPersonIds = $user->assignedAccounts()->where('is_active', true)->pluck('person_id');
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        // 1. My Collection Accounts Due Today
        $dueToday = Invoice::whereIn('status', ['draft', 'issued', 'partially_paid'])
            ->where('due_date', $today)
            ->whereHas('project', function ($q) use ($assignedPersonIds) {
                $q->whereIn('person_id', $assignedPersonIds);
            })->count();

        // 2. My Collection Accounts Overdue
        $overdue = Invoice::whereIn('status', ['draft', 'issued', 'partially_paid'])
            ->where('due_date', '<', $today)
            ->whereHas('project', function ($q) use ($assignedPersonIds) {
                $q->whereIn('person_id', $assignedPersonIds);
            })->count();

        // 3. Unassigned Collections Overdue
        // Find invoices that are overdue, and their project's person has NO active collector assignment.
        $unassignedOverdue = Invoice::whereIn('status', ['draft', 'issued', 'partially_paid'])
            ->where('due_date', '<', $today)
            ->whereHas('project.person', function ($q) {
                // does not have active collector
                $q->whereDoesntHave('activeCollectorAssignment', function ($q2) {
                    $q2->where('is_active', true);
                });
            })->count();

        // 4. My Collection QIDs Expiring This Month
        $qidExpiringMonth = Person::whereIn('id', $assignedPersonIds)
            ->whereNotNull('id_expiration_date')
            ->whereRaw("DATE_FORMAT(id_expiration_date, '%Y-%m') = ?", [$thisMonth])
            ->where('id_expiration_date', '>=', $today) // hasn't expired yet
            ->count();

        // 5. My Collection QIDs Expired
        $qidExpired = Person::whereIn('id', $assignedPersonIds)
            ->whereNotNull('id_expiration_date')
            ->where('id_expiration_date', '<', $today)
            ->count();

        // 6. All Expired QIDs
        $allExpiredQids = Person::whereNotNull('id_expiration_date')
            ->where('id_expiration_date', '<', $today)
            ->count();

        return response()->json([
            'due_today' => $dueToday,
            'overdue' => $overdue,
            'unassigned_overdue' => $unassignedOverdue,
            'qid_expiring_month' => $qidExpiringMonth,
            'qid_expired' => $qidExpired,
            'all_expired_qids' => $allExpiredQids,
        ]);
    }

    public function queue(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('Collector')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->type; // 'due_today', 'overdue', 'unassigned_overdue'
        $assignedPersonIds = $user->assignedAccounts()->where('is_active', true)->pluck('person_id');
        $today = now()->toDateString();

        $query = Invoice::with([
            'project:id,project_code,type,person_id',
            'project.person:id,name,phone,qatar_id,id_expiration_date,company_id',
            'project.person.company:id,name'
        ])->whereIn('status', ['draft', 'issued', 'partially_paid']);

        if ($type === 'due_today') {
            $query->where('due_date', $today)
                ->whereHas('project', function ($q) use ($assignedPersonIds) {
                    $q->whereIn('person_id', $assignedPersonIds);
                });
        } elseif ($type === 'overdue') {
            $query->where('due_date', '<', $today)
                ->whereHas('project', function ($q) use ($assignedPersonIds) {
                    $q->whereIn('person_id', $assignedPersonIds);
                });
        } elseif ($type === 'unassigned_overdue') {
            $query->where('due_date', '<', $today)
                ->whereHas('project.person', function ($q) {
                    $q->whereDoesntHave('activeCollectorAssignment', function ($q2) {
                        $q2->where('is_active', true);
                    });
                });
        } else {
            return response()->json(['message' => 'Invalid queue type'], 400);
        }

        return response()->json($query->latest('due_date')->paginate(10));
    }

    public function qids(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('Collector')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->type; // 'expiring_month', 'expired', 'all_expired'
        $assignedPersonIds = $user->assignedAccounts()->where('is_active', true)->pluck('person_id');
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        $query = Person::query()->whereNotNull('id_expiration_date');

        if ($type === 'expiring_month') {
            $query->whereIn('id', $assignedPersonIds)
                  ->whereRaw("DATE_FORMAT(id_expiration_date, '%Y-%m') = ?", [$thisMonth])
                  ->where('id_expiration_date', '>=', $today);
        } elseif ($type === 'expired') {
            $query->whereIn('id', $assignedPersonIds)
                  ->where('id_expiration_date', '<', $today);
        } elseif ($type === 'all_expired') {
            $query->where('id_expiration_date', '<', $today);
        } else {
            return response()->json(['message' => 'Invalid queue type'], 400);
        }

        return response()->json($query->orderBy('id_expiration_date', 'asc')->paginate(10));
    }
}
