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
        Schema::create('contest_entries', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('contest_id');

    $table->unsignedBigInteger('user_id');

    $table->unsignedBigInteger('team_id');

    $table->decimal('points',10,2)->default(0);

    $table->integer('rank')->nullable();

    $table->timestamps();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_entries');
    }
};
