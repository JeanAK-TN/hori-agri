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
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // Sans la surface, aucun indicateur à l'hectare (SPEC 6.2).
            $table->decimal('area_ha', 6, 3);
            $table->string('soil_type', 20);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('previous_crop', 60)->nullable();
            $table->timestamps();

            $table->index(['cooperative_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plots');
    }
};
