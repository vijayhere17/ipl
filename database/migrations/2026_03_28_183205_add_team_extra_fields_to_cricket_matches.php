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
    $table->string('team1_code')->nullable();
    $table->string('team2_code')->nullable();
    $table->string('team1_logo')->nullable();
    $table->string('team2_logo')->nullable();
    $table->string('winner')->nullable();
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
