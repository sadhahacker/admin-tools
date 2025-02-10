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
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('side');
            $table->timestamp('open_time');
            $table->decimal('entry_price');
            $table->decimal('take_profit')->nullable();
            $table->decimal('stop_loss')->nullable();
            $table->string('status', 50);
            $table->boolean('successful');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
