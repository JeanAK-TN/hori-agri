<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The technical itinerary of a cycle (SPEC section 6.2).
     */
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('crop_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // Obligatoire dès qu'un intrant est apporté (SPEC section 6.3) :
            // sans lui, sept règles sur dix-sept ne peuvent pas s'évaluer.
            $table->string('input_type', 20)->nullable();
            $table->date('date');
            // Nom commercial, jamais lu par le moteur.
            $table->string('product', 80)->nullable();
            // Pour la parcelle entière, telle que le technicien la compte.
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit', 20)->nullable();
            // En francs CFA.
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['crop_cycle_id', 'date']);
            $table->index(['cooperative_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
