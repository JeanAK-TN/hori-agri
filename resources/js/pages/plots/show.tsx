import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Sprout } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import type { CycleStatusValue } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create as createCycle, show as showCycle } from '@/routes/cycles';
import { show as showFarmer } from '@/routes/farmers';
import { edit, index } from '@/routes/plots';

type Plot = {
    id: number;
    name: string;
    area_ha: string;
    soil_type_label: string;
    soil_preparation_label: string | null;
    latitude: string | null;
    longitude: string | null;
    previous_crop: string | null;
    farmer: {
        id: number;
        full_name: string;
        village: string;
        phone: string | null;
    };
};

type Cycle = {
    id: number;
    crop: string;
    campaign: string;
    sowing_date: string;
    status: CycleStatusValue;
    status_label: string;
};

type Props = {
    plot: Plot;
    cycles: Cycle[];
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

export default function PlotShow({ plot, cycles }: Props) {
    const coordinates =
        plot.latitude && plot.longitude
            ? `${plot.latitude} ; ${plot.longitude}`
            : 'Non relevées';

    return (
        <>
            <Head title={plot.name} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={plot.name}
                    description={`${plot.area_ha} ha · ${plot.farmer.full_name} (${plot.farmer.village})`}
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>
                    </Button>
                    <Button asChild className="h-12">
                        <Link href={edit(plot.id)}>
                            <Pencil className="size-4" />
                            Modifier
                        </Link>
                    </Button>
                </PageHeader>

                <section className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="font-heading text-lg font-semibold">
                        Caractéristiques
                    </h2>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Surface" value={`${plot.area_ha} ha`} />
                        <Detail
                            label="Type de sol"
                            value={plot.soil_type_label}
                        />
                        <Detail
                            label="Préparation du sol"
                            value={
                                plot.soil_preparation_label ?? 'Non renseignée'
                            }
                        />
                        <Detail
                            label="Culture précédente"
                            value={plot.previous_crop ?? 'Non renseignée'}
                        />
                        <Detail label="Coordonnées" value={coordinates} />
                    </dl>

                    <Button asChild variant="outline" className="h-12">
                        <Link href={showFarmer(plot.farmer.id)}>
                            Voir la fiche du producteur
                        </Link>
                    </Button>
                </section>

                <section className="max-w-2xl space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-heading text-lg font-semibold">
                            Cycles culturaux
                        </h2>
                        <Button asChild className="h-12">
                            <Link
                                href={createCycle({
                                    query: { parcelle: plot.id },
                                })}
                            >
                                <Plus className="size-4" />
                                Ouvrir un cycle
                            </Link>
                        </Button>
                    </div>

                    {cycles.length === 0 ? (
                        <EmptyState
                            icon={Sprout}
                            title="Aucun cycle sur cette parcelle"
                            description="Ouvrez un cycle pour suivre une culture sur une campagne."
                        />
                    ) : (
                        <ul className="space-y-3">
                            {cycles.map((cycle) => (
                                <li key={cycle.id}>
                                    <Link
                                        href={showCycle(cycle.id)}
                                        className="flex min-h-14 items-center justify-between gap-3 rounded-lg border p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {cycle.crop}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {cycle.campaign} · semis le{' '}
                                                {cycle.sowing_date}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={cycle.status}
                                            label={cycle.status_label}
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

PlotShow.layout = {
    breadcrumbs: [{ title: 'Parcelles', href: index() }],
};
