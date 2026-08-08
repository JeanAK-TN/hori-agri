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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->string('label', 40);
            // Bornes basse et haute des dates acceptées dans un cycle.
            $table->date('start_date');
            $table->date('end_date');
            $table->string('season_type', 20);
            $table->timestamps();

            // Deux campagnes homonymes dans une même coopérative rendraient
            // les listes de choix ambiguës pour le technicien.
            $table->unique(['cooperative_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
