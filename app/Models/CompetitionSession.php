<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionSession extends Model
{
    protected $fillable = ['competition_id', 'code', 'pin_hash', 'state', 'timer_started_at', 'timer_ends_at', 'paused_remaining_ms', 'current_participant_id', 'question_number', 'settings', 'rules_snapshot', 'checklist_confirmed_at', 'checklist_confirmed_by', 'finished_at'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['timer_started_at' => 'datetime', 'timer_ends_at' => 'datetime', 'finished_at' => 'datetime', 'checklist_confirmed_at' => 'datetime', 'settings' => 'array', 'rules_snapshot' => 'array'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class)->orderBy('position');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(QuizTeam::class)->orderBy('id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(BuzzerRound::class)->orderByDesc('number');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(KnockoutMatch::class)->orderBy('round_number')->orderBy('match_number');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ScoreTransaction::class)->latest();
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CompetitionStage::class)->orderBy('sequence')->orderBy('heat_number')->orderBy('attempt_number');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(CompetitionIncident::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(SessionPlacement::class)->orderBy('rank');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(CompetitionAuditLog::class);
    }

    public function competitionType(): string
    {
        $settings = $this->settings ?? [];

        if (isset($settings['competition_type'])) {
            return $settings['competition_type'];
        }

        return ! empty($settings['timer_mode']) ? 'general' : $this->competition->type;
    }
}
