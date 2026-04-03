<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qid_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('current_expiry_date');
            $table->enum('status', ['expiring', 'expired', 'in_progress', 'renewed'])->default('expiring');
            $table->date('renewed_on')->nullable();
            $table->string('latest_document_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qid_renewals');
    }
};
