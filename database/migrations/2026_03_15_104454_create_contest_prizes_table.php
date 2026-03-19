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
        Schema::create('contest_prizes', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('contest_id');

    $table->integer('rank_from');

    $table->integer('rank_to');

    $table->decimal('prize_amount',10,2);

    $table->string('extra_prize')->nullable();

    $table->timestamps();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_prizes');
    }
};
