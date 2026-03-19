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
    Schema::create('match_players', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cricket_match_id');
        $table->unsignedBigInteger('player_id');
        $table->decimal('credit', 5, 2); // credit for this specific match
        $table->boolean('is_playing')->default(false); // confirmed playing XI
        $table->timestamps();

        $table->foreign('cricket_match_id')
              ->references('id')
              ->on('cricket_matches')
              ->onDelete('cascade');

        $table->foreign('player_id')
              ->references('id')
              ->on('players')
              ->onDelete('cascade');

        $table->unique(['cricket_match_id', 'player_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
