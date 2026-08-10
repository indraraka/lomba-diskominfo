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
        Schema::create('competition_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6)->unique();
            $table->string('pin_hash');
            $table->enum('state', ['lobby', 'ready', 'running', 'paused', 'finished'])->default('lobby');
            $table->timestamp('timer_started_at')->nullable();
            $table->timestamp('timer_ends_at')->nullable();
            $table->unsignedBigInteger('paused_remaining_ms')->nullable();
            $table->unsignedBigInteger('current_participant_id')->nullable();
            $table->unsignedInteger('question_number')->default(0);
            $table->json('settings')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_sessions');
    }
};
