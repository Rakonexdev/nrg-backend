<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_code')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->enum('status', ['open', 'invoiced'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreignId('timesheet_group_id')->nullable()->after('id')->constrained('timesheet_groups')->nullOnDelete();
            $table->foreignId('project_profession_id')->nullable()->after('project_id')->constrained('project_professions')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->after('project_profession_id')->constrained('persons')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('invoice_code');
            $table->foreignId('timesheet_group_id')->nullable()->after('project_id')->constrained('timesheet_groups')->nullOnDelete();
            $table->string('evidence_url')->nullable()->after('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timesheet_group_id');
            $table->dropColumn(['reference_number', 'evidence_url']);
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timesheet_group_id');
            $table->dropConstrainedForeignId('project_profession_id');
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('timesheet_groups');
    }
};
