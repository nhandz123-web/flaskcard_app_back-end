<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DeckController;
use App\Http\Controllers\Api\LearnController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout',[AuthController::class, 'logout']);

    // Decks (chỉ thấy/đụng data của chính mình)
    Route::get('/decks',           [DeckController::class, 'index']);
    Route::post('/decks',          [DeckController::class, 'store']);
    Route::get('/decks/{deck}',    [DeckController::class, 'show']);
    Route::put('/decks/{deck}',    [DeckController::class, 'update']);
    Route::delete('/decks/{deck}', [DeckController::class, 'destroy']);
    Route::post('/cards/{cardId}/upload-image', [CardController::class, 'uploadImage']);
    Route::post('/cards/{cardId}/audio', [CardController::class, 'uploadAudio']);
    Route::put('/decks/{deckId}/cards/{card}', [CardController::class, 'updateCardDetails']);

    // Cards theo deck
    Route::get('/decks/{deck}/cards',             [CardController::class, 'index']);
    Route::post('/decks/{deck}/cards',            [CardController::class, 'store']);
    Route::get('/decks/{deck}/cards/{card}',      [CardController::class, 'show']);
    Route::put('/decks/{deck}/cards/{card}',      [CardController::class, 'update']);
    Route::delete('/decks/{deck}/cards/{card}',   [CardController::class, 'destroy']);
    Route::post('/cards/{card}/upload-image', [CardController::class, 'uploadImage']);
    Route::post('/cards/{card}/audio', [CardController::class, 'uploadAudio']);
    Route::put('/decks/{deckId}/cards/{card}', [CardController::class, 'updateCardDetails']);

    Route::get('/decks/{deck}/learn', [App\Http\Controllers\Api\LearnController::class, 'getCardsToReview']);
    Route::post('/decks/{deck}/cards/{card}/progress', [App\Http\Controllers\Api\LearnController::class, 'updateProgress']);
});




