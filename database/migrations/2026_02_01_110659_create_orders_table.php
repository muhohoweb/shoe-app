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
        Schema::create('orders', function (Blueprint $table) {
            // Customer details (optional if no auth)
            $table->id()->primary();
            $table->uuid('uuid')->unique();
            $table->string('customer_name')->nullable();

            // Payment & M-Pesa
            $table->string('mpesa_number');
            $table->string('mpesa_code')->nullable(); // e.g. MPESA transaction ID
            $table->decimal('amount', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');

            //tracking details
            $table->text('tracking_number')->nullable();
            // Address / Pickup info
            $table->string('town');
            $table->longText('description');
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
