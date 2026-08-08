<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The crop registry is common to every cooperative: it deliberately
     * carries no cooperative_id (SPEC section 7.1).
     */
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('family', 60)->nullable();
            $table->integer('cycle_days');
            $table->integer('reference_density');
            $table->decimal('reference_yield_kg_ha', 8, 2);
            $table->json('npk_needs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
