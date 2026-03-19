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
   Schema::create('cricket_matches', function (Blueprint $table) {
    $table->id();

    $table->string('api_match_id')->unique();

    $table->string('series_name')->nullable();

    $table->string('team_1');
    $table->string('team_2');

    $table->dateTime('match_start_time')->index();

    $table->enum('status', [
        'upcoming',
        'live',
        'completed',
        'cancelled'
    ])->default('upcoming')->index();

    $table->boolean('is_locked')->default(false);

    $table->timestamps();
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cricket_matches');
    }
};
