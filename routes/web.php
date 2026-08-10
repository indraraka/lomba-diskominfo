<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\CompetitionWorkflowController;
use App\Http\Controllers\GeneralSessionController;
use App\Http\Controllers\GuestQuizController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDisplayController;
use App\Http\Controllers\QuizSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::get('/r/{code}', [GuestQuizController::class, 'show'])->name('guest.quiz');
Route::post('/r/{code}/join', [GuestQuizController::class, 'join'])->middleware('throttle:60,1')->name('guest.join');
Route::get('/r/{code}/state', [GuestQuizController::class, 'state'])->middleware('throttle:120,1')->name('guest.state');
Route::post('/r/{code}/buzz', [GuestQuizController::class, 'buzz'])->middleware('throttle:120,1')->name('guest.buzz');
Route::get('/display/{code}', PublicDisplayController::class)->name('sessions.display');
Route::get('/display/{code}/state', [PublicDisplayController::class, 'state'])->middleware('throttle:120,1')->name('sessions.display.state');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [CompetitionController::class, 'index'])->name('dashboard');
    Route::get('/settings', [AppSettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [AppSettingController::class, 'update']);
    Route::patch('/settings', [AppSettingController::class, 'update'])->name('settings.update');
    Route::resource('competitions', CompetitionController::class);
    Route::post('/competitions/{competition}/clone', [CompetitionController::class, 'duplicate'])->name('competitions.clone');
    Route::post('/competitions/{competition}/sessions', [GeneralSessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{session}/control', [GeneralSessionController::class, 'control'])->name('sessions.control');
    Route::post('/sessions/{session}/participants', [GeneralSessionController::class, 'participants'])->name('sessions.participants');
    Route::patch('/sessions/{session}/participants/{participant}', [GeneralSessionController::class, 'updateParticipant'])->name('sessions.participants.update');
    Route::patch('/sessions/{session}/duration', [GeneralSessionController::class, 'duration'])->name('sessions.duration');
    Route::delete('/sessions/{session}/participants/{participant}', [GeneralSessionController::class, 'removeParticipant'])->name('sessions.participants.destroy');
    Route::post('/sessions/{session}/timer', [GeneralSessionController::class, 'timer'])->name('sessions.timer');
    Route::patch('/sessions/{session}/participants/{participant}/result', [GeneralSessionController::class, 'result'])->name('sessions.result');
    Route::post('/sessions/{session}/general-bracket', [GeneralSessionController::class, 'generateBracket'])->name('general.bracket.generate');
    Route::post('/sessions/{session}/general-matches/{match}/activate', [GeneralSessionController::class, 'activateMatch'])->name('general.matches.activate');
    Route::post('/sessions/{session}/general-matches/{match}/complete', [GeneralSessionController::class, 'completeMatch'])->name('general.matches.complete');
    Route::post('/sessions/{session}/general-matches/{match}/score', [GeneralSessionController::class, 'scoreMatch'])->name('general.matches.score');
    Route::post('/sessions/{session}/checklist', [CompetitionWorkflowController::class, 'checklist'])->name('workflow.checklist');
    Route::post('/sessions/{session}/prepare', [CompetitionWorkflowController::class, 'prepare'])->name('workflow.prepare');
    Route::post('/sessions/{session}/stages/{stage}/timer', [CompetitionWorkflowController::class, 'timer'])->name('workflow.stage.timer');
    Route::patch('/sessions/{session}/stages/{stage}/result', [CompetitionWorkflowController::class, 'result'])->name('workflow.stage.result');
    Route::post('/sessions/{session}/incidents', [CompetitionWorkflowController::class, 'incident'])->name('workflow.incident');
    Route::post('/sessions/{session}/stages/{stage}/advance-elimination', [CompetitionWorkflowController::class, 'advanceElimination'])->name('workflow.elimination.advance');
    Route::post('/sessions/{session}/replay', [CompetitionWorkflowController::class, 'replay'])->name('workflow.replay');
    Route::post('/sessions/{session}/finalize', [CompetitionWorkflowController::class, 'finalize'])->name('workflow.finalize');
    Route::post('/sessions/{session}/teams', [QuizSessionController::class, 'teams'])->name('quiz.teams');
    Route::delete('/sessions/{session}/teams/{team}', [QuizSessionController::class, 'removeTeam'])->name('quiz.teams.destroy');
    Route::post('/sessions/{session}/teams/{team}/approve', [QuizSessionController::class, 'approve'])->name('quiz.approve');
    Route::post('/sessions/{session}/rounds', [QuizSessionController::class, 'nextRound'])->name('quiz.rounds');
    Route::post('/sessions/{session}/mandatory', [QuizSessionController::class, 'mandatory'])->name('quiz.mandatory');
    Route::post('/sessions/{session}/tiebreak', [QuizSessionController::class, 'tiebreak'])->name('quiz.tiebreak');
    Route::post('/sessions/{session}/rankings', [QuizSessionController::class, 'finalizeRankings'])->name('quiz.rankings');
    Route::post('/sessions/{session}/teams/{team}/external-help', [QuizSessionController::class, 'externalHelp'])->name('quiz.external-help');
    Route::post('/sessions/{session}/buzzer/open', [QuizSessionController::class, 'open'])->name('quiz.open');
    Route::post('/sessions/{session}/score', [QuizSessionController::class, 'score'])->name('quiz.score');
    Route::post('/sessions/{session}/scores/{transaction}/reverse', [QuizSessionController::class, 'reverse'])->name('quiz.score.reverse');
    Route::post('/sessions/{session}/finish', [QuizSessionController::class, 'finish'])->name('quiz.finish');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
