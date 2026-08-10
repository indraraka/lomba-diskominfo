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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->enum('type', ['general', 'quiz']);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->enum('timer_mode', ['individual', 'collective'])->nullable();
            $table->enum('result_type', ['number', 'time'])->nullable();
            $table->string('result_label')->nullable();
            $table->string('result_unit')->nullable();
            $table->enum('ranking_direction', ['asc', 'desc'])->default('desc');
            $table->integer('correct_points')->default(10);
            $table->integer('wrong_points')->default(-5);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
