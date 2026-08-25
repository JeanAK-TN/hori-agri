<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les quatre ajouts révélés par la vérification du modèle au
     * chapitre 3 du mémoire, plus la note d'origine du seuil
     * (SPEC-CORRECTION sections 3.3 et 5).
     *
     * Le maraîchage se conduit en deux temps, pépinière puis repiquage :
     * la fiche togolaise compte le calendrier de fertilisation à partir
     * du repiquage, pas du semis. Le tuteurage, la préparation du sol et
     * le taux observé décrivent des pratiques réelles du terrain ; aucune
     * règle active ne les lit aujourd'hui, mais ils figurent au
     * dictionnaire de données du mémoire.
     */
    public function up(): void
    {
        Schema::table('crop_cycles', function (Blueprint $table): void {
            $table->date('transplanting_date')->nullable()->after('sowing_date');
            $table->boolean('staked')->nullable()->after('density');
        });

        Schema::table('plots', function (Blueprint $table): void {
            // Le sol du site d'INSECTGO, sablo_argilo_limoneux, dépasse
            // les vingt caractères d'origine (SPEC-CORRECTION section 5.6).
            $table->string('soil_type', 30)->change();
            $table->string('soil_preparation', 30)->nullable()->after('soil_type');
        });

        Schema::table('operations', function (Blueprint $table): void {
            $table->decimal('observed_rate', 8, 2)->nullable()->after('cost');
        });

        Schema::table('rules', function (Blueprint $table): void {
            $table->string('threshold_note', 60)->nullable()->after('threshold_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crop_cycles', function (Blueprint $table): void {
            $table->dropColumn(['transplanting_date', 'staked']);
        });

        Schema::table('plots', function (Blueprint $table): void {
            $table->dropColumn('soil_preparation');
            $table->string('soil_type', 20)->change();
        });

        Schema::table('operations', function (Blueprint $table): void {
            $table->dropColumn('observed_rate');
        });

        Schema::table('rules', function (Blueprint $table): void {
            $table->dropColumn('threshold_note');
        });
    }
};
