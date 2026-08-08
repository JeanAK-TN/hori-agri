<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The output of the engine (SPEC section 6.2). The action, the
     * source and the origin of the threshold are read through the rule,
     * never copied here.
     */
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->restrictOnDelete();
            $table->foreignId('crop_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            // Rang après tri, 1 étant le plus prioritaire.
            $table->integer('priority');
            // Le message de la règle complété par les valeurs observées.
            $table->text('generated_message');
            $table->string('status', 20);
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->unique(['crop_cycle_id', 'rule_id']);
            $table->index(['cooperative_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
