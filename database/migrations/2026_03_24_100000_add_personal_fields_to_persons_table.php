<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('id_photo_url');
            $table->string('nationality', 100)->nullable()->after('date_of_birth');
            $table->string('passport_number', 50)->nullable()->after('nationality');
            $table->date('passport_validity')->nullable()->after('passport_number');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'nationality', 'passport_number', 'passport_validity']);
        });
    }
};
