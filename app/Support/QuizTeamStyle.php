<?php

namespace App\Support;

class QuizTeamStyle
{
    private const STYLES = [
        ['color' => '#dc2626', 'pitch' => 330],
        ['color' => '#2563eb', 'pitch' => 440],
        ['color' => '#16a34a', 'pitch' => 554],
        ['color' => '#ea580c', 'pitch' => 659],
        ['color' => '#9333ea', 'pitch' => 784],
        ['color' => '#0891b2', 'pitch' => 988],
    ];

    public static function at(int $index): array
    {
        return self::STYLES[$index % count(self::STYLES)];
    }
}
