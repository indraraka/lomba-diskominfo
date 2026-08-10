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
        Schema::create('buzzer_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->enum('state', ['ready', 'open', 'locked', 'judging', 'closed'])->default('ready');
            $table->timestamp('opens_at')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('quiz_teams')->nullOnDelete();
            $table->json('eliminated_team_ids')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_session_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buzzer_rounds');
    }
};
