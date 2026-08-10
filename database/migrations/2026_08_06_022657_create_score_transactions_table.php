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
        Schema::create('score_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buzzer_round_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['correct', 'wrong', 'adjustment', 'reversal']);
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('reason')->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('score_transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_transactions');
    }
};
