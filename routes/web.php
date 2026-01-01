<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProposalController;

Route::get('/', [ProposalController::class, 'index']);
Route::post('/generate', [ProposalController::class, 'generate'])
    ->name('proposal.generate');

Route::get('/test-openai', function () {
    $res = Http::withToken(env('OPENAI_API_KEY'))
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ]);

    return $res->json();
});

