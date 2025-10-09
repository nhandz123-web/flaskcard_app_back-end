<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardProgressesTable extends Migration
{
    public function up()
    {
        Schema::create('card_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('card_id')->constrained()->onDelete('cascade');
            $table->integer('repetition')->default(0);
            $table->integer('interval')->default(1);
            $table->float('ease_factor')->default(2.5);
            $table->timestamp('next_review_at')->nullable();
            $table->integer('correct_count')->default(0);
            $table->integer('incorrect_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('card_progresses');
    }
}