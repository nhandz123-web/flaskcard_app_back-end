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
        Schema::create('pronunciation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('card_id')->constrained()->onDelete('cascade');
            $table->text('expected_text');
            $table->text('transcribed_text');
            $table->decimal('accuracy', 5, 2); // 0-100
            $table->integer('stars')->default(1); // 1-5
            $table->string('grade')->nullable(); // Xuất sắc, Tốt, etc.
            $table->text('feedback')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'card_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pronunciation_history');
    }
};