<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The pivot class of the model: it brings together a plot, a crop, an
     * optional variety and a campaign (SPEC section 6).
     */
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained()->restrictOnDelete();
            // Souvent inconnue du producteur.
            $table->foreignId('variety_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            // Point de départ de toutes les règles de calendrier.
            $table->date('sowing_date');
            $table->integer('density')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['plot_id', 'crop_id', 'campaign_id']);
            $table->index(['cooperative_id', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
