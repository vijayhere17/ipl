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
    Schema::create('fantasy_teams', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('cricket_match_id');
        $table->string('team_name')->nullable();
        $table->decimal('total_points', 10, 2)->default(0);
        $table->timestamps();

        $table->foreign('user_id')
              ->references('id')
              ->on('users')
              ->onDelete('cascade');

        $table->foreign('cricket_match_id')
              ->references('id')
              ->on('cricket_matches')
              ->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fantasy_teams');
    }
};
