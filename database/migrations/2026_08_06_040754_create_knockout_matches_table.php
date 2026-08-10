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
        Schema::create('knockout_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('match_number');
            $table->foreignId('participant_a_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->foreignId('participant_b_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->foreignId('winner_participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->enum('state', ['pending', 'active', 'completed'])->default('pending');
            $table->timestamps();
            $table->unique(['competition_session_id', 'round_number', 'match_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knockout_matches');
    }
};
