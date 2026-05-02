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
        Schema::create('player_match_points', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('player_id');
    $table->unsignedBigInteger('match_id');
    $table->float('points')->default(0);
    $table->timestamps();

    $table->unique(['player_id', 'match_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_match_points');
    }
};
