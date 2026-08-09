<?php

namespace App\Services\Dashboard;

/**
 * Les trois regroupements du tableau de bord (SPEC section 12).
 *
 * L'énumération sert aussi de liste blanche : la colonne de groupement
 * part littéralement d'ici et jamais d'un paramètre de requête.
 */
enum Grouping: string
{
    case Crop = 'crop';
    case Village = 'village';
    case Campaign = 'campaign';

    /**
     * @return literal-string
     */
    public function column(): string
    {
        return match ($this) {
            self::Crop => 'crops.name',
            self::Village => 'farmers.village',
            self::Campaign => 'campaigns.label',
        };
    }

    /**
     * Libellé de la colonne « Regroupement » de l'export.
     */
    public function label(): string
    {
        return match ($this) {
            self::Crop => 'Culture',
            self::Village => 'Village',
            self::Campaign => 'Campagne',
        };
    }
}
