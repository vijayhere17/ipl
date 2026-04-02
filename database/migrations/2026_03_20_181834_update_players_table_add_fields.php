<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('players', function (Blueprint $table) {
        $table->string('team_name')->nullable()->after('role');
        $table->unsignedBigInteger('cricket_match_id')->nullable()->after('team_name');
        $table->string('image')->nullable()->change(); // already exists but ensure usable
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
