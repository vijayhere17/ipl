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
            $table->enum('contest_type', ['public', 'private'])
                  ->default('public')
                  ->after('name');

            $table->string('private_code')
                  ->nullable()
                  ->after('contest_type');
        });
    }

    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->dropColumn(['contest_type', 'private_code']);
        });
    }
};
