<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeNextReviewDateToTimestampInCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            // Chuyển next_review_date sang TIMESTAMP, giữ nullable
            $table->timestamp('next_review_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            // Quay lại DATETIME nếu rollback
            $table->dateTime('next_review_date')->nullable()->change();
        });
    }
}