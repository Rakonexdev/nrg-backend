<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    public function roles()
    {
        return response()->json(Role::with('permissions:id,name')->get());
    }

    public function permissions()
    {
        return response()->json(Permission::all());
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json($role->load('permissions:id,name'));
    }

    public function getMenus()
    {
        $roles = Role::with('permissions:id,name')->get();
        $permissions = Permission::all()->groupBy(function ($perm) {
            return explode('.', $perm->name)[0] ?? 'general';
        });

        return response()->json([
            'roles' => $roles,
            'permission_groups' => $permissions,
        ]);
    }

    public function updateMenus(Request $request)
    {
        $validated = $request->validate([
            'role_permissions' => 'required|array',
            'role_permissions.*.role_id' => 'required|exists:roles,id',
            'role_permissions.*.permissions' => 'array',
            'role_permissions.*.permissions.*' => 'string|exists:permissions,name',
        ]);

        foreach ($validated['role_permissions'] as $rp) {
            $role = Role::findById($rp['role_id']);
            $role->syncPermissions($rp['permissions'] ?? []);
        }

        return response()->json(['message' => 'Menu permissions updated successfully']);
    }
}
