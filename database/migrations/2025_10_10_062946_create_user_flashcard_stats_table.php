<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserFlashcardStatsTable extends Migration
{
    public function up(): void
    {
        Schema::create('user_flashcard_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('flashcards_learned')->default(0); // Số flashcard đã học
            $table->integer('words_mastered')->default(0); // Số từ vựng thành thạo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_flashcard_stats');
    }
}