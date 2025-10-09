<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_progresses', function (Blueprint $table) {
            $table->index('next_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('card_progresses', function (Blueprint $table) {
            $table->dropIndex(['next_review_at']);
        });
    }
};