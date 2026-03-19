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
    Schema::create('match_player_stats', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cricket_match_id');
        $table->unsignedBigInteger('player_id');
        $table->integer('runs')->default(0);
        $table->integer('wickets')->default(0);
        $table->integer('catches')->default(0);
        $table->decimal('fantasy_points', 10, 2)->default(0);
        $table->timestamps();

        $table->foreign('cricket_match_id')->references('id')->on('cricket_matches')->onDelete('cascade');
        $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
        $table->unique(['cricket_match_id', 'player_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_player_stats');
    }
};
