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
            $table->date('project_date')->nullable()->after('project_code');
        });

        // Set existing projects' project_date to their created_at date
        DB::table('projects')->whereNull('project_date')->update([
            'project_date' => DB::raw('DATE(created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('project_date');
        });
    }
};
