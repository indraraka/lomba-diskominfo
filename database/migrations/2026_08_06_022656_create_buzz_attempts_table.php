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
        Schema::create('buzz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buzzer_round_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_team_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->enum('result', ['accepted', 'too_early', 'locked', 'eliminated', 'not_approved']);
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buzz_attempts');
    }
};
