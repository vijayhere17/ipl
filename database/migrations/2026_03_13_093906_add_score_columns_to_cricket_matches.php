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
        Schema::table('cricket_matches', function (Blueprint $table) {

    $table->integer('team1_score')->nullable();
    $table->integer('team1_wicket')->nullable();
    $table->string('team1_over')->nullable();

    $table->integer('team2_score')->nullable();
    $table->integer('team2_wicket')->nullable();
    $table->string('team2_over')->nullable();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cricket_matches', function (Blueprint $table) {
            //
        });
    }
};
