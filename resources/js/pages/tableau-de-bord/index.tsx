import { Head, router } from '@inertiajs/react';
import {
    Coins,
    Download,
    Scale,
    Sprout,
    TrendingDown,
    Wheat,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import HorizontalBarChart from '@/components/horizontal-bar-chart';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useChartColors } from '@/hooks/use-chart-colors';
import {
    exportMethod as exportCsv,
    index,
} from '@/routes/exploitation-dashboard';

type Average = {
    label: string;
    cycles: number;
    yield_kg_ha: number | null;
    production_cost_ha: number | null;
    gross_margin_ha: number | null;
};

type Props = {
    headline: {
        cycles: number;
        yield_kg_ha: number | null;
        production_cost_ha: number | null;
        gross_margin_ha: number | null;
    };
    byCrop: Average[];
    byVillage: Average[];
    byCampaign: Average[];
    bySeverity: { severity: string; label: string; total: number }[];
    appliedShare: { applied: number; answered: number; rate: number | null };
    belowZoneMedian: { below: number; comparable: number };
    filters: { campagne: number | null; culture: number | null };
    campaigns: { id: number; label: string }[];
    crops: { id: number; label: string }[];
};

const ALL = 'toutes';

const NUMBER = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

function amount(value: number | null, unit: string): string {
    return value === null ? 'Incalculable' : `${NUMBER.format(value)} ${unit}`;
}

function percentage(rate: number | null): string {
    return rate === null ? 'Sans objet' : `${NUMBER.format(rate * 100)} %`;
}

/**
 * Une section de graphique et sa table : la table n'est pas un doublon,
 * c'est la lecture accessible des mêmes chiffres.
 */
function AverageSection({
    title,
    description,
    rows,
}: {
    title: string;
    description: string;
    rows: Average[];
}) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4 rounded-xl border bg-card p-4 md:p-6">
            <div className="space-y-1">
                <h2 className="font-heading text-lg font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>

            <HorizontalBarChart
                labels={rows.map((row) => row.label)}
                values={rows.map((row) => row.yield_kg_ha ?? 0)}
                unit="kg/ha"
                ariaLabel={title}
            />

            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{title.split(' ').pop()}</TableHead>
                            <TableHead className="text-right">Cycles</TableHead>
                            <TableHead className="text-right">
                                Rendement
                            </TableHead>
                            <TableHead className="text-right">Coût</TableHead>
                            <TableHead className="text-right">Marge</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.label}>
                                <TableCell className="font-medium">
                                    {row.label}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.cycles}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {amount(row.yield_kg_ha, 'kg/ha')}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {amount(row.production_cost_ha, 'FCFA/ha')}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {amount(row.gross_margin_ha, 'FCFA/ha')}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}

export default function ExploitationDashboard({
    headline,
    byCrop,
    byVillage,
    byCampaign,
    bySeverity,
    appliedShare,
    belowZoneMedian,
    filters,
    campaigns,
    crops,
}: Props) {
    const theme = useChartColors();

    const applyFilters = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        router.get(
            index().url,
            {
                campagne: merged.campagne ?? undefined,
                culture: merged.culture ?? undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const severityRamp = [
        theme['--chart-severity-1'],
        theme['--chart-severity-2'],
        theme['--chart-severity-3'],
    ];

    const hasRecommendations = bySeverity.some((row) => row.total > 0);

    return (
        <>
            <Head title="Tableau de bord" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Tableau de bord"
                    description="Ce que les cycles de l'exploitation disent, une fois mis bout à bout."
                >
                    <Button variant="outline" className="h-12" asChild>
                        <a
                            href={
                                exportCsv({
                                    query: {
                                        campagne: filters.campagne ?? undefined,
                                        culture: filters.culture ?? undefined,
                                    },
                                }).url
                            }
                            data-test="export-csv"
                        >
                            <Download className="size-4" />
                            Exporter la synthèse
                        </a>
                    </Button>
                </PageHeader>

                {/* Les filtres en une ligne, au dessus de tout le reste. */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="campagne">Campagne</Label>
                        <Select
                            value={
                                filters.campagne === null
                                    ? ALL
                                    : String(filters.campagne)
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    campagne:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger id="campagne" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Toutes</SelectItem>
                                {campaigns.map((campaign) => (
                                    <SelectItem
                                        key={campaign.id}
                                        value={String(campaign.id)}
                                    >
                                        {campaign.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="culture">Culture</Label>
                        <Select
                            value={
                                filters.culture === null
                                    ? ALL
                                    : String(filters.culture)
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    culture:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger id="culture" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Toutes</SelectItem>
                                {crops.map((crop) => (
                                    <SelectItem
                                        key={crop.id}
                                        value={String(crop.id)}
                                    >
                                        {crop.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {headline.cycles === 0 ? (
                    <EmptyState
                        icon={Sprout}
                        title="Rien à agréger pour l'instant"
                        description="Les moyennes apparaissent dès qu'un cycle porte une récolte. Saisissez les opérations et les récoltes, le reste se calcule."
                    />
                ) : (
                    <>
                        <div
                            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                            data-test="headline"
                        >
                            <StatCard
                                label="Cycles pris en compte"
                                value={NUMBER.format(headline.cycles)}
                                hint="Les cycles abandonnés sont écartés."
                                icon={Sprout}
                            />
                            <StatCard
                                label="Rendement moyen"
                                value={amount(headline.yield_kg_ha, 'kg/ha')}
                                icon={Wheat}
                            />
                            <StatCard
                                label="Coût moyen"
                                value={amount(
                                    headline.production_cost_ha,
                                    'FCFA/ha',
                                )}
                                icon={Coins}
                            />
                            <StatCard
                                label="Marge brute moyenne"
                                value={amount(
                                    headline.gross_margin_ha,
                                    'FCFA/ha',
                                )}
                                hint="Nulle tant qu'un prix de vente manque."
                                icon={Scale}
                            />
                        </div>

                        <AverageSection
                            title="Rendement moyen par culture"
                            description="Moyenne des rendements des cycles récoltés, culture par culture."
                            rows={byCrop}
                        />

                        <AverageSection
                            title="Rendement moyen par village"
                            description="Là où les conditions se ressemblent, les écarts tiennent surtout à la conduite."
                            rows={byVillage}
                        />

                        <AverageSection
                            title="Rendement moyen par campagne"
                            description="D'une saison à l'autre, à conditions comparables."
                            rows={byCampaign}
                        />

                        <div className="grid gap-6 lg:grid-cols-2">
                            <section className="space-y-4 rounded-xl border bg-card p-4 md:p-6">
                                <div className="space-y-1">
                                    <h2 className="font-heading text-lg font-semibold">
                                        Recommandations par sévérité
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Une seule teinte, du foncé au clair : la
                                        sévérité se lit comme une échelle.
                                    </p>
                                </div>

                                {hasRecommendations ? (
                                    <>
                                        <HorizontalBarChart
                                            labels={bySeverity.map(
                                                (row) => row.label,
                                            )}
                                            values={bySeverity.map(
                                                (row) => row.total,
                                            )}
                                            colors={severityRamp}
                                            ariaLabel="Recommandations par sévérité"
                                        />
                                        <ul
                                            className="space-y-1 text-sm"
                                            data-test="severity-counts"
                                        >
                                            {bySeverity.map((row) => (
                                                <li
                                                    key={row.severity}
                                                    className="flex items-center justify-between"
                                                >
                                                    <span>{row.label}</span>
                                                    <span className="tabular-nums">
                                                        {NUMBER.format(
                                                            row.total,
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Aucune recommandation sur le périmètre
                                        choisi.
                                    </p>
                                )}
                            </section>

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                <StatCard
                                    label="Part des conseils appliqués"
                                    value={percentage(appliedShare.rate)}
                                    hint={`${NUMBER.format(appliedShare.applied)} appliquées sur ${NUMBER.format(appliedShare.answered)} auxquelles un producteur a répondu.`}
                                />
                                <StatCard
                                    label="Parcelles sous la médiane de leur zone"
                                    value={NUMBER.format(belowZoneMedian.below)}
                                    hint={`Sur ${NUMBER.format(belowZoneMedian.comparable)} cycles dont la zone compte assez de parcelles pour comparer.`}
                                    icon={TrendingDown}
                                />
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ExploitationDashboard.layout = {
    breadcrumbs: [{ title: 'Tableau de bord', href: index() }],
};
