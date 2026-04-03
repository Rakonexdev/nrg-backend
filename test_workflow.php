<?php
require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Project;
use App\Models\ProjectProfession;
use App\Models\TimesheetGroup;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;

// 1. Create a dummy user
$user = User::firstOrCreate(['email' => 'test@example.com'], [
    'name' => 'Test User',
    'password' => bcrypt('password'),
]);

// 2. Create person
$person = Person::firstOrCreate(['phone' => '00000000'], [
    'name' => 'Simulated Person',
    'qatar_id' => '00000000000',
    'id_expiration_date' => now()->addYear(),
]);

// 3. Create a variable project
$project = Project::create([
    'project_code' => Project::generateCode(),
    'person_id' => $person->id,
    'type' => 'variable',
    'status' => 'draft',
    'created_by' => $user->id,
]);

// 4. Create profession
$profession = ProjectProfession::create([
    'project_id' => $project->id,
    'profession_name' => 'Engineer',
    'name' => 'John Doe',
    'hourly_rate' => 100,
    'date_of_join' => now()->subDays(10),
    'status' => 'active',
]);

// 5. Create Timesheet Group manually instead of controller to just have the data
$group = TimesheetGroup::create([
    'group_code' => TimesheetGroup::generateCode(),
    'project_id' => $project->id,
    'status' => 'open',
]);

// Add timesheet row
$group->timesheets()->create([
    'project_id' => $project->id,
    'project_profession_id' => $profession->id,
    'person_id' => $person->id,
    'date_logged' => now(),
    'profession_name' => $profession->profession_name,
    'rate_per_hour' => $profession->hourly_rate,
    'total_hours' => 10,
    'date_from' => now()->subDays(5),
    'date_to' => now()->subDays(1),
    'total_price' => 1000,
    'created_by' => $user->id,
]);

echo "Timesheet Group Initial Status: " . $group->status . "\n";
echo "Timesheet Group Initial Invoice ID: " . ($group->invoice_id ?? 'NULL') . "\n";

// 6. Simulate InvoiceController store
$requestData = [
    'project_id' => $project->id,
    'timesheet_group_id' => $group->id,
    'reference_number' => 'REF-SIM-001',
    'total_amount' => 1000,
];

// Execute logic directly
$invoice = Invoice::create([
    'project_id' => $project->id,
    'invoice_code' => Invoice::generateCode(),
    'reference_number' => $requestData['reference_number'],
    'timesheet_group_id' => $group->id,
    'total_amount' => 1000,
    'issued_at' => now(),
    'created_by' => $user->id,
    'status' => 'issued',
    'due_date' => now()->addDays(15),
    'deduction_amount' => 0,
]);

// Controller logic that updates the group
TimesheetGroup::where('id', $requestData['timesheet_group_id'])->update([
    'invoice_id' => $invoice->id,
    'status' => 'invoiced',
    'closed_at' => now(),
]);

$project->update(['status' => 'invoiced']);

$group->refresh();
echo "Timesheet Group Final Status: " . $group->status . "\n";
echo "Timesheet Group Final Invoice ID: " . ($group->invoice_id ?? 'NULL') . "\n";
echo "Project Final Status: " . $project->status . "\n";

// Cleanup
$invoice->delete();
$group->timesheets()->delete();
$group->delete();
$profession->delete();
$project->delete();
$person->delete();
