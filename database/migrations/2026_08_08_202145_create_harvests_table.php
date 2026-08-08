<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Several harvests are possible for one cycle (SPEC section 6.2).
     */
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('crop_cycle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('quantity_kg', 10, 2);
            // Prix de vente au kilogramme, en francs CFA.
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('quality', 20)->nullable();
            $table->decimal('post_harvest_losses_kg', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['crop_cycle_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
