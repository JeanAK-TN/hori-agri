import { Info, TrendingDown, TrendingUp } from 'lucide-react';
import { cn } from '@/lib/utils';

export type CycleIndicators = {
    yield_kg_ha: string | null;
    production_cost_ha: string | null;
    gross_margin_ha: string | null;
    input_cost_share: string | null;
    zone_median_yield_kg_ha: string | null;
    zone_median_cost_ha: string | null;
    zone_plot_count: number;
    yield_vs_zone_median: string | null;
    cost_vs_zone_median: string | null;
    has_comparable_zone: boolean;
    minimum_zone_size: number;
    computed_at_label: string;
};

type Props = {
    indicators: CycleIndicators;
};

const NUMBER = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
});

function format(value: string | null, suffix: string): string {
    if (value === null) {
        return 'Incalculable';
    }

    return `${NUMBER.format(Number(value))} ${suffix}`;
}

function Figure({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-heading text-xl font-semibold tabular-nums">
                {value}
            </dd>
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
        </div>
    );
}

/**
 * Comparaison au village : un rapport supérieur à 1 place la parcelle
 * au-dessus de la médiane de sa zone. Le sens de lecture s'inverse entre
 * le rendement, qu'on veut haut, et le coût, qu'on veut bas.
 */
function Comparison({
    label,
    ratio,
    higherIsBetter,
}: {
    label: string;
    ratio: string | null;
    higherIsBetter: boolean;
}) {
    if (ratio === null) {
        return null;
    }

    const value = Number(ratio);
    const percent = Math.round((value - 1) * 100);
    const isFavourable = higherIsBetter ? value >= 1 : value <= 1;
    const Icon = value >= 1 ? TrendingUp : TrendingDown;

    return (
        <div className="flex items-center gap-3">
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    isFavourable
                        ? 'bg-severity-low text-severity-low-foreground'
                        : 'bg-severity-medium text-severity-medium-foreground',
                )}
            >
                <Icon className="size-5" />
            </span>
            <div>
                <p className="font-medium tabular-nums">
                    {percent > 0 ? '+' : ''}
                    {percent} %
                </p>
                <p className="text-sm text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

export default function CycleIndicatorsPanel({ indicators }: Props) {
    const share = indicators.input_cost_share;

    return (
        <div className="space-y-6">
            <dl className="grid gap-4 sm:grid-cols-2">
                <Figure
                    label="Rendement"
                    value={format(indicators.yield_kg_ha, 'kg/ha')}
                />
                <Figure
                    label="Coût de production"
                    value={format(indicators.production_cost_ha, 'FCFA/ha')}
                />
                <Figure
                    label="Marge brute"
                    value={format(indicators.gross_margin_ha, 'FCFA/ha')}
                    hint={
                        indicators.gross_margin_ha === null
                            ? 'Renseignez le prix de vente de chaque récolte.'
                            : undefined
                    }
                />
                <Figure
                    label="Part des intrants"
                    value={
                        share === null
                            ? 'Incalculable'
                            : `${Math.round(Number(share) * 100)} % de la récolte`
                    }
                />
            </dl>

            <div className="space-y-3 border-t pt-4">
                <h3 className="font-medium">Comparaison à la zone</h3>

                {indicators.has_comparable_zone ? (
                    <>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {indicators.zone_plot_count} cycles comparables ·
                            médiane de rendement{' '}
                            {format(
                                indicators.zone_median_yield_kg_ha,
                                'kg/ha',
                            )}
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Comparison
                                label="de rendement face à la médiane"
                                ratio={indicators.yield_vs_zone_median}
                                higherIsBetter
                            />
                            <Comparison
                                label="de coût face à la médiane"
                                ratio={indicators.cost_vs_zone_median}
                                higherIsBetter={false}
                            />
                        </div>
                    </>
                ) : (
                    <div className="flex items-start gap-3 rounded-lg border border-dashed p-4">
                        <Info className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            La comparaison reste suspendue :{' '}
                            {indicators.zone_plot_count} cycle
                            {indicators.zone_plot_count > 1 ? 's' : ''}{' '}
                            comparable
                            {indicators.zone_plot_count > 1 ? 's' : ''} au lieu
                            des {indicators.minimum_zone_size} nécessaires. En
                            dessous de ce seuil, une médiane classerait une
                            parcelle correctement conduite du mauvais côté.
                        </p>
                    </div>
                )}
            </div>

            <p className="text-sm text-muted-foreground">
                Calculé le {indicators.computed_at_label}, sans action de votre
                part.
            </p>
        </div>
    );
}
