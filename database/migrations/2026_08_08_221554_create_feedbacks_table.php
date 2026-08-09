<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ce que l'agriculteur a fait de la recommandation (SPEC section 6.2).
     * Un seul retour par recommandation : il se corrige, il ne s'empile
     * pas.
     */
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('recommendation_id')->unique()->constrained()->cascadeOnDelete();
            // Doit être le propriétaire de la parcelle concernée : la
            // policy le vérifie, la colonne ne vient jamais du formulaire.
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->boolean('applied');
            $table->text('comment')->nullable();
            $table->text('observed_result')->nullable();
            $table->timestamps();

            $table->index(['cooperative_id', 'applied']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
