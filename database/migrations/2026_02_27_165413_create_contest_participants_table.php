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
    Schema::create('contest_participants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('contest_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('fantasy_team_id');
        $table->decimal('total_points', 10, 2)->default(0);
        $table->integer('rank')->nullable();
        $table->timestamps();

        $table->foreign('contest_id')->references('id')->on('contests')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('fantasy_team_id')->references('id')->on('fantasy_teams')->onDelete('cascade');

        $table->unique(['contest_id', 'user_id', 'fantasy_team_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_participants');
    }
};
