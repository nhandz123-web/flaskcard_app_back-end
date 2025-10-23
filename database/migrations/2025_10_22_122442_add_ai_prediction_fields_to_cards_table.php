<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // === Trường lịch sử review ===
            $table->json('review_history')->nullable()->after('next_review_date');
            $table->integer('total_reviews')->default(0)->after('review_history');
            $table->timestamp('last_reviewed_at')->nullable()->after('total_reviews');
            $table->integer('correct_streak')->default(0)->after('last_reviewed_at');
            $table->integer('total_correct')->default(0)->after('correct_streak');
            
            // === Trường AI Prediction (MỚI) ===
            // Khả năng quên hiện tại (0-100%)
            $table->decimal('forgetting_probability', 5, 2)->nullable()->after('total_correct');
            
            // Độ khó của thẻ theo AI (easy, medium, hard, very_hard)
            $table->string('ai_difficulty', 20)->nullable()->after('forgetting_probability');
            
            // Lý do AI đưa ra dự đoán (để debug/hiểu rõ hơn)
            $table->text('ai_reasoning')->nullable()->after('ai_difficulty');
            
            // Lưu lịch sử các lần dự đoán của AI (JSON array)
            // [{probability: 45.2, difficulty: "medium", predicted_at: "2025-01-15 10:00:00"}]
            $table->json('ai_prediction_history')->nullable()->after('ai_reasoning');
            
            // Thời điểm dự đoán gần nhất
            $table->timestamp('last_ai_prediction_at')->nullable()->after('ai_prediction_history');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'review_history',
                'total_reviews',
                'last_reviewed_at',
                'correct_streak',
                'total_correct',
                'forgetting_probability',
                'ai_difficulty',
                'ai_reasoning',
                'ai_prediction_history',
                'last_ai_prediction_at',
            ]);
        });
    }
};