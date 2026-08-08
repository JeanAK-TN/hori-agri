<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Computed values, one set per cycle (SPEC section 6.2). None of
     * them is ever typed in by a user.
     */
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('crop_cycle_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('yield_kg_ha', 10, 2)->nullable();
            $table->decimal('production_cost_ha', 10, 2)->nullable();
            $table->decimal('gross_margin_ha', 12, 2)->nullable();
            $table->decimal('input_cost_share', 5, 3)->nullable();
            $table->decimal('zone_median_yield_kg_ha', 10, 2)->nullable();
            $table->decimal('zone_median_cost_ha', 10, 2)->nullable();
            $table->integer('zone_plot_count')->default(0);
            // Nuls tant que la zone compte moins de cinq cycles
            // comparables (SPEC section 9.3).
            $table->decimal('yield_vs_zone_median', 6, 3)->nullable();
            $table->decimal('cost_vs_zone_median', 6, 3)->nullable();
            $table->dateTime('computed_at');
            $table->timestamps();

            $table->index(['cooperative_id', 'zone_plot_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
