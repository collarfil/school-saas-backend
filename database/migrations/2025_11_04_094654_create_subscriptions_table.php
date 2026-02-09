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
        Schema::create('subscriptions', function (Blueprint $table) {
           $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('plan_type')->default('termly');
            $table->string('term')->nullable();
            $table->string('school_session')->nullable();
            $table->integer('student_capacity')->default(100);
            $table->decimal('amount', 10, 2);
            $table->string('payment_reference')->unique()->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('payment_gateway')->default('paystack');
            $table->json('payment_response')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status')->default('inactive');
            $table->timestamps();
            $table->softDeletes();

        });
   }

    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
