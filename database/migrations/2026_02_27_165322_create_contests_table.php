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
    Schema::create('contests', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cricket_match_id');
        $table->string('name');
        $table->decimal('entry_fee', 10, 2);
        $table->integer('total_slots');
        $table->integer('filled_slots')->default(0);
        $table->decimal('prize_pool', 10, 2);
        $table->decimal('platform_fee', 10, 2)->default(0);
        $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
        $table->timestamps();

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
        Schema::dropIfExists('contests');
    }
};
