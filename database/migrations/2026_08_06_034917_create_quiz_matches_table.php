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
        Schema::create('quiz_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('match_number');
            $table->foreignId('team_a_id')->nullable()->constrained('quiz_teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('quiz_teams')->nullOnDelete();
            $table->foreignId('winner_team_id')->nullable()->constrained('quiz_teams')->nullOnDelete();
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
        Schema::dropIfExists('quiz_matches');
    }
};
