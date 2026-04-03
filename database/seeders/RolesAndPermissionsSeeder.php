<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions grouped by module
        $permissions = [
            // Users
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            // Roles & ALC
            'roles.view', 'roles.update', 'menus.view', 'menus.update',
            // Companies
            'companies.view', 'companies.create',
            // Persons
            'persons.view', 'persons.create', 'persons.update',
            // Projects
            'projects.view', 'projects.create', 'projects.update', 'projects.status',
            // Timesheets
            'timesheets.view', 'timesheets.create', 'timesheets.update', 'timesheets.delete',
            // Invoices
            'invoices.view', 'invoices.create',
            // Collections
            'collections.view', 'collections.create', 'collections.verify',
            // Expenses
            'expenses.view', 'expenses.create', 'expense_categories.view', 'expense_categories.create', 'expense_categories.update',
            // Reports
            'reports.outstanding_invoices', 'reports.collections_summary', 'reports.collections_feed',
            'reports.expenses_by_category', 'reports.audit_logs',
            // Uploads
            'uploads.id_photo', 'uploads.lpo', 'uploads.invoice_copy', 'uploads.expense_document',
            // Audit
            'audit_logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->syncPermissions(Permission::all());

        $customerRelation = Role::firstOrCreate(['name' => 'Customer Relation Staff']);
        $customerRelation->syncPermissions([
            'companies.view', 'companies.create',
            'persons.view', 'persons.create', 'persons.update',
            'projects.view', 'projects.create', 'projects.update', 'projects.status',
            'timesheets.view', 'timesheets.create', 'timesheets.update', 'timesheets.delete',
            'invoices.view',
            'collections.view',
            'uploads.id_photo', 'uploads.lpo',
        ]);

        $accountant = Role::firstOrCreate(['name' => 'Accountant']);
        $accountant->syncPermissions([
            'companies.view', 'persons.view',
            'projects.view',
            'invoices.view', 'invoices.create',
            'collections.view', 'collections.create', 'collections.verify',
            'expenses.view', 'expenses.create',
            'expense_categories.view', 'expense_categories.create', 'expense_categories.update',
            'reports.expenses_by_category', 'reports.outstanding_invoices', 'reports.collections_summary',
            'uploads.invoice_copy', 'uploads.expense_document',
        ]);

        $collector = Role::firstOrCreate(['name' => 'Collector']);
        $collector->syncPermissions([
            'companies.view', 'persons.view',
            'projects.view',
            'invoices.view',
            'collections.view', 'collections.create',
            'reports.collections_feed',
        ]);

        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $manager->syncPermissions([
            'companies.view', 'persons.view',
            'projects.view',
            'invoices.view',
            'collections.view',
            'expenses.view',
            'reports.outstanding_invoices', 'reports.collections_summary', 'reports.collections_feed',
            'reports.expenses_by_category',
        ]);
    }
}
