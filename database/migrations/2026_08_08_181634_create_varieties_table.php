<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Like crops, the variety registry is common to every cooperative.
     */
    public function up(): void
    {
        Schema::create('varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->integer('cycle_days')->nullable();
            $table->decimal('potential_yield_kg_ha', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['crop_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('varieties');
    }
};
