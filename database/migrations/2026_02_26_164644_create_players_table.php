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
    Schema::create('players', function (Blueprint $table) {
        $table->id();
        $table->string('api_player_id')->unique(); // ID from cricket API
        $table->string('name');
        $table->string('team_name');
        $table->enum('role', [
            'batsman',
            'bowler',
            'all_rounder',
            'wicket_keeper'
        ]);
        $table->decimal('credit', 5, 2)->default(8.0); // fantasy credit value
        $table->string('image')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
