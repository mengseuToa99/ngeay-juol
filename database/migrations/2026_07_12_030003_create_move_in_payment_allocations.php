<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('move_in_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_move_in_requirement_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('amount_usd', 12, 2)->default(0);
            $table->decimal('amount_khr', 12, 0)->default(0);
            $table->timestamps();
            $table->index('rental_move_in_requirement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('move_in_payment_allocations'); }
};
