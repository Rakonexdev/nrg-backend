<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','quoted','invoiced','active','operationally_completed','financially_closed','cancelled') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY status ENUM('draft','quoted','active','operationally_completed','financially_closed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
