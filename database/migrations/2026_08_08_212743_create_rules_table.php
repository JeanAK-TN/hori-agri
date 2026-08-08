<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The knowledge base (SPEC section 6.2). An agronomic rule is a row,
     * never a line of PHP: the condition is a tree of conditions read
     * from here (rule 4).
     */
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            // Nul pour une règle commune à toutes les coopératives.
            $table->foreignId('cooperative_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            // Nul pour une règle applicable à toute culture.
            $table->foreignId('crop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('family', 1);
            $table->json('condition');
            $table->string('severity', 10);
            $table->string('message', 255);
            $table->text('action');
            $table->string('estimated_gain', 120)->nullable();
            $table->string('source', 255);
            $table->string('threshold_status', 20);
            $table->boolean('enabled');
            $table->timestamps();

            $table->index(['cooperative_id', 'enabled']);
        });

        // Unicité du couple (cooperative_id, code), la valeur nulle de
        // cooperative_id comptant comme une valeur distincte. PostgreSQL
        // l'exprime par deux index uniques partiels (SPEC section 6.2).
        DB::statement(
            'CREATE UNIQUE INDEX rules_common_code_unique ON rules (code) WHERE cooperative_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX rules_cooperative_code_unique ON rules (cooperative_id, code) WHERE cooperative_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rules_common_code_unique');
        DB::statement('DROP INDEX IF EXISTS rules_cooperative_code_unique');

        Schema::dropIfExists('rules');
    }
};
