<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuzzAttempt extends Model
{
    protected $fillable = ['buzzer_round_id', 'quiz_team_id', 'idempotency_key', 'result', 'received_at'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
