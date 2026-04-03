<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('project_code');
            }
            if (!Schema::hasColumn('projects', 'bill_frequency')) {
                $table->string('bill_frequency')->nullable()->after('fixed_description');
            }
            if (!Schema::hasColumn('projects', 'payment_credit_days')) {
                $table->integer('payment_credit_days')->nullable()->after('bill_frequency');
            }
            if (!Schema::hasColumn('projects', 'operationally_completed_at')) {
                $table->date('operationally_completed_at')->nullable()->after('payment_credit_days');
            }
            if (!Schema::hasColumn('projects', 'financially_closed_at')) {
                $table->date('financially_closed_at')->nullable()->after('operationally_completed_at');
            }
        });

        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','quoted','active','operationally_completed','financially_closed','cancelled') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','active','on_hold','completed','withdrawn') NOT NULL DEFAULT 'draft'");
    }
};
