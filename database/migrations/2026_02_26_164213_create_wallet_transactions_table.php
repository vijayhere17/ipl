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
    Schema::create('wallet_transactions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->enum('type', [
            'deposit',
            'bonus_credit',
            'contest_entry',
            'winning_credit',
            'withdrawal'
        ]);
        $table->decimal('amount', 12, 2);
        $table->enum('wallet_type', [
            'deposit',
            'bonus',
            'winning'
        ]);
        $table->string('reference_id')->nullable(); // contest_id or payment_id
        $table->text('description')->nullable();
        $table->timestamps();

        $table->foreign('user_id')
              ->references('id')
              ->on('users')
              ->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
