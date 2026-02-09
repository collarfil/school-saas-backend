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
    Schema::create('fee_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fee_id')->constrained()->onDelete('cascade');
    $table->foreignId('student_id')->constrained()->onDelete('cascade');
    $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
    $table->decimal('amount_paid', 10, 2);
    $table->date('payment_date');
    $table->string('status')->default('paid');
    $table->timestamps();
    $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
