<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_professions', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->after('no_of_persons');
            $table->timestamp('deactivated_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_professions', function (Blueprint $table) {
            $table->dropColumn(['status', 'deactivated_at']);
        });
    }
};
