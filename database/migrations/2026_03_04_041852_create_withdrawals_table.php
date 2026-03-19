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
    Schema::create('withdrawals', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 10, 2);

    $table->string('wallet_address');
    $table->string('network')->default('TRC20');

    $table->enum('status', ['pending','approved','rejected'])
        ->default('pending');

    $table->text('admin_note')->nullable();
    $table->timestamp('processed_at')->nullable();

    $table->timestamps();
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
