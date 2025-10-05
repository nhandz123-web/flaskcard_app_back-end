<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->integer('cards_count')->default(0);
            $table->unsignedInteger('cards_count')->default(0)->check('cards_count >= 0');
        });
        
    }

    public function down()
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->dropColumn('cards_count');
        });
    }
};
