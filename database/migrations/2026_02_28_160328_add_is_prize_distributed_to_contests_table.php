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
    Schema::table('contests', function (Blueprint $table) {
        $table->boolean('is_prize_distributed')
              ->default(false)
              ->after('status');
    });
}

public function down(): void
{
    Schema::table('contests', function (Blueprint $table) {
        $table->dropColumn('is_prize_distributed');
    });
}
};
