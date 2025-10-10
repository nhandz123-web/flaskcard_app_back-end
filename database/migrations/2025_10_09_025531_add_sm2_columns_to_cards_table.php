<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSm2ColumnsToCardsTable extends Migration
{
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->double('easiness')->default(2.5)->after('example');
            $table->integer('repetition')->default(0)->after('easiness');
            $table->integer('interval')->default(1)->after('repetition');
            $table->dateTime('next_review_date')->nullable()->after('interval');
        });
    }

    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['easiness', 'repetition', 'interval', 'next_review_date']);
        });
    }
}