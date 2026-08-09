<?php

namespace App\Services\Dashboard;

use Illuminate\Http\Request;

/**
 * Les filtres du tableau de bord, lus une fois et passés partout.
 *
 * L'écran et l'export les reçoivent identiques : c'est ce qui garantit
 * que le fichier reprend exactement ce qui est affiché (SPEC section 12).
 */
final readonly class DashboardFilters
{
    public function __construct(
        public ?int $campaignId = null,
        public ?int $cropId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            campaignId: $request->integer('campagne') ?: null,
            cropId: $request->integer('culture') ?: null,
        );
    }

    /**
     * @return array{campagne: int|null, culture: int|null}
     */
    public function toArray(): array
    {
        return ['campagne' => $this->campaignId, 'culture' => $this->cropId];
    }
}
