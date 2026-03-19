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
    Schema::create('fantasy_team_players', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('fantasy_team_id');
        $table->unsignedBigInteger('player_id');
        $table->boolean('is_captain')->default(false);
        $table->boolean('is_vice_captain')->default(false);
        $table->timestamps();

        $table->foreign('fantasy_team_id')
              ->references('id')
              ->on('fantasy_teams')
              ->onDelete('cascade');

        $table->foreign('player_id')
              ->references('id')
              ->on('players')
              ->onDelete('cascade');

        $table->unique(['fantasy_team_id', 'player_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fantasy_team_players');
    }
};
