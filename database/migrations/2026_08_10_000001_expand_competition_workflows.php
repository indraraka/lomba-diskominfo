<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('workflow_key')->default('manual_rank')->after('type');
            $table->enum('entry_kind', ['individual', 'team'])->default('individual')->after('workflow_key');
            $table->string('schedule_text')->nullable()->after('description');
            $table->string('venue')->nullable()->after('schedule_text');
            $table->string('person_in_charge')->nullable()->after('venue');
            $table->json('rules')->nullable()->after('person_in_charge');
            $table->boolean('is_official')->default(false)->after('rules');
            $table->string('source_version')->nullable()->after('is_official');
        });

        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->json('rules_snapshot')->nullable()->after('settings');
            $table->timestamp('checklist_confirmed_at')->nullable()->after('rules_snapshot');
            $table->foreignId('checklist_confirmed_by')->nullable()->after('checklist_confirmed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->string('affiliation')->nullable()->after('name');
            $table->json('members')->nullable()->after('affiliation');
            $table->unsignedInteger('final_rank')->nullable()->after('result_value');
            $table->json('result_meta')->nullable()->after('final_rank');
        });

        Schema::table('quiz_teams', function (Blueprint $table) {
            $table->string('affiliation')->nullable()->after('name');
            $table->json('members')->nullable()->after('affiliation');
            $table->unsignedInteger('final_rank')->nullable()->after('score');
        });

        Schema::table('buzzer_rounds', function (Blueprint $table) {
            $table->string('phase')->default('rebuttal')->after('number');
            $table->unsignedInteger('answer_attempt')->default(0)->after('phase');
            $table->unsignedInteger('max_attempts')->default(2)->after('answer_attempt');
            $table->json('eligible_team_ids')->nullable()->after('eliminated_team_ids');
        });

        Schema::table('knockout_matches', function (Blueprint $table) {
            $table->string('bracket_type')->default('main')->after('competition_session_id');
            $table->unsignedInteger('score_a')->default(0)->after('participant_b_id');
            $table->unsignedInteger('score_b')->default(0)->after('score_a');
        });

        Schema::create('competition_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('heat_number')->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('label');
            $table->enum('state', ['draft', 'ready', 'running', 'paused', 'completed', 'void'])->default('draft');
            $table->enum('timer_kind', ['none', 'countdown', 'stopwatch'])->default('none');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('paused_remaining_ms')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['competition_session_id', 'phase', 'sequence', 'heat_number', 'attempt_number'], 'stages_identity_unique');
        });

        Schema::create('stage_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('outcome', ['pending', 'passed', 'failed', 'finished', 'timeout', 'eliminated', 'disqualified', 'safety_stop', 'void'])->default('pending');
            $table->decimal('primary_value', 14, 3)->nullable();
            $table->decimal('secondary_value', 14, 3)->nullable();
            $table->bigInteger('penalty_ms')->default(0);
            $table->integer('score_delta')->default(0);
            $table->json('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['competition_stage_id', 'participant_id'], 'stage_participant_unique');
            $table->unique(['competition_stage_id', 'quiz_team_id'], 'stage_team_unique');
        });

        Schema::create('competition_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quiz_team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_code');
            $table->enum('kind', ['warning', 'time_penalty', 'point_penalty', 'disqualification', 'equipment_failure', 'safety_stop', 'jury_decision']);
            $table->integer('value')->nullable();
            $table->text('reason');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('session_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->json('result_summary')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['competition_session_id', 'rank'], 'placements_session_rank_unique');
        });

        Schema::create('competition_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_audit_logs');
        Schema::dropIfExists('session_placements');
        Schema::dropIfExists('competition_incidents');
        Schema::dropIfExists('stage_results');
        Schema::dropIfExists('competition_stages');

        Schema::table('knockout_matches', fn (Blueprint $table) => $table->dropColumn(['bracket_type', 'score_a', 'score_b']));
        Schema::table('buzzer_rounds', fn (Blueprint $table) => $table->dropColumn(['phase', 'answer_attempt', 'max_attempts', 'eligible_team_ids']));
        Schema::table('quiz_teams', fn (Blueprint $table) => $table->dropColumn(['affiliation', 'members', 'final_rank']));
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn(['affiliation', 'members', 'final_rank', 'result_meta']));
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_confirmed_by');
            $table->dropColumn(['rules_snapshot', 'checklist_confirmed_at']);
        });
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn(['workflow_key', 'entry_kind', 'schedule_text', 'venue', 'person_in_charge', 'rules', 'is_official', 'source_version']));
    }
};
