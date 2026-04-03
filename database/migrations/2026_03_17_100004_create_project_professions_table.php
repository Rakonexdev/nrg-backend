<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_professions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('profession_name');
            $table->decimal('hourly_rate', 12, 2);
            $table->integer('no_of_persons');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_professions');
    }
};
