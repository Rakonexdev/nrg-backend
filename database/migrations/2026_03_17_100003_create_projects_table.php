<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->enum('type', ['fixed', 'variable']);
            $table->string('reference_number')->nullable();
            $table->enum('status', ['draft', 'quoted', 'active', 'operationally_completed', 'financially_closed', 'cancelled'])->default('draft');
            $table->string('lpo_url')->nullable();
            $table->decimal('fixed_total_amount', 12, 2)->nullable();
            $table->text('fixed_description')->nullable();
            $table->string('bill_frequency')->nullable();
            $table->integer('payment_credit_days')->nullable();
            $table->date('operationally_completed_at')->nullable();
            $table->date('financially_closed_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
