<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_professions', function (Blueprint $table) {
            $table->string('name')->nullable()->after('profession_name');
            $table->date('date_of_join')->nullable()->after('hourly_rate');
            $table->integer('no_of_persons')->default(1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_professions', function (Blueprint $table) {
            $table->dropColumn(['name', 'date_of_join']);
            $table->integer('no_of_persons')->nullable(false)->default(null)->change();
        });
    }
};
