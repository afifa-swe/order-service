<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('pickup_point_id')->nullable();
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('house', 20)->nullable();
            $table->string('apartment', 20)->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
