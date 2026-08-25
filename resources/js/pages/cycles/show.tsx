import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ChartNoAxesColumn,
    ClipboardList,
    Wheat,
} from 'lucide-react';
import CropCycleController from '@/actions/App/Http/Controllers/CropCycleController';
import CycleIndicatorsPanel from '@/components/cycle-indicators';
import type { CycleIndicators } from '@/components/cycle-indicators';
import EmptyState from '@/components/empty-state';
import HarvestForm from '@/components/harvest-form';
import OperationForm from '@/components/operation-form';
import type { Option, OperationTypeOption } from '@/components/operation-form';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import type { CycleStatusValue } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/cycles';
import { show as showFarmer } from '@/routes/farmers';
import { show as showPlot } from '@/routes/plots';

type Cycle = {
    id: number;
    crop: string;
    variety: string | null;
    campaign: string;
    campaign_start_label: string;
    campaign_end_label: string;
    sowing_date_label: string;
    transplanting_date_label: string | null;
    density: number | null;
    staked_label: string | null;
    status: CycleStatusValue;
    status_label: string;
    is_open: boolean;
    plot: {
        id: number;
        name: string;
        area_ha: string;
        soil_type_label: string;
    };
    farmer: { id: number; full_name: string; village: string };
};

type OperationRow = {
    id: number;
    type: string;
    type_label: string;
    input_type_label: string | null;
    date_label: string;
    product: string | null;
    quantity: string | null;
    unit_label: string | null;
    cost: string | null;
    observed_rate: string | null;
    notes: string | null;
};

type HarvestRow = {
    id: number;
    date_label: string;
    quantity_kg: string;
    unit_price: string | null;
    quality_label: string | null;
    post_harvest_losses_kg: string | null;
};

type Props = {
    cycle: Cycle;
    indicators: CycleIndicators | null;
    operations: OperationRow[];
    harvests: HarvestRow[];
    operationTypes: OperationTypeOption[];
    inputTypes: Option[];
    units: Option[];
    qualities: Option[];
    canRecord: boolean;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

export default function CycleShow({
    cycle,
    indicators,
    operations,
    harvests,
    operationTypes,
    inputTypes,
    units,
    qualities,
    canRecord,
}: Props) {
    return (
        <>
            <Head title={`${cycle.crop} · ${cycle.plot.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={`${cycle.crop} sur ${cycle.plot.name}`}
                    description={`${cycle.campaign} · ${cycle.farmer.full_name} (${cycle.farmer.village})`}
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>
                    </Button>
                </PageHeader>

                <section className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-heading text-lg font-semibold">
                            Le cycle
                        </h2>
                        <StatusBadge
                            status={cycle.status}
                            label={cycle.status_label}
                        />
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Culture" value={cycle.crop} />
                        <Detail
                            label="Variété"
                            value={cycle.variety ?? 'Inconnue'}
                        />
                        <Detail
                            label="Date de semis"
                            value={cycle.sowing_date_label}
                        />
                        <Detail
                            label="Date de repiquage"
                            value={
                                cycle.transplanting_date_label ??
                                'Non renseignée'
                            }
                        />
                        <Detail
                            label="Densité"
                            value={
                                cycle.density === null
                                    ? 'Non renseignée'
                                    : `${cycle.density} plants/ha`
                            }
                        />
                        <Detail
                            label="Conduite"
                            value={cycle.staked_label ?? 'Non renseignée'}
                        />
                        <Detail
                            label="Campagne"
                            value={`${cycle.campaign_start_label} au ${cycle.campaign_end_label}`}
                        />
                        <Detail
                            label="Parcelle"
                            value={`${cycle.plot.name} · ${cycle.plot.area_ha} ha · ${cycle.plot.soil_type_label}`}
                        />
                    </dl>

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="h-12">
                            <Link href={showPlot(cycle.plot.id)}>
                                Voir la parcelle
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="h-12">
                            <Link href={showFarmer(cycle.farmer.id)}>
                                Voir le producteur
                            </Link>
                        </Button>
                    </div>
                </section>

                {indicators && (
                    <section className="max-w-2xl space-y-4 rounded-xl border bg-card p-4 md:p-6">
                        <h2 className="flex items-center gap-2 font-heading text-lg font-semibold">
                            <ChartNoAxesColumn className="size-5" />
                            Indicateurs
                        </h2>
                        <CycleIndicatorsPanel indicators={indicators} />
                    </section>
                )}

                <section className="max-w-2xl space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="flex items-center gap-2 font-heading text-lg font-semibold">
                        <ClipboardList className="size-5" />
                        Itinéraire technique
                    </h2>

                    {/* Les opérations déjà saisies s'affichent en ordre
                        chronologique au-dessus du formulaire (section 11.1). */}
                    {operations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aucune opération enregistrée pour l'instant.
                        </p>
                    ) : (
                        <ol className="space-y-3" data-test="operation-list">
                            {operations.map((operation) => (
                                <li
                                    key={operation.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <p className="font-medium">
                                            {operation.type_label}
                                        </p>
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {operation.date_label}
                                        </span>
                                    </div>

                                    {operation.input_type_label && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {operation.input_type_label}
                                            </Badge>
                                            {operation.product && (
                                                <span className="text-sm text-muted-foreground">
                                                    {operation.product}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    {(operation.quantity || operation.cost) && (
                                        <p className="text-sm text-muted-foreground tabular-nums">
                                            {operation.quantity &&
                                                `${operation.quantity} ${operation.unit_label ?? ''}`}
                                            {operation.quantity &&
                                                operation.cost &&
                                                ' · '}
                                            {operation.cost &&
                                                `${operation.cost} FCFA`}
                                        </p>
                                    )}

                                    {operation.observed_rate && (
                                        <p className="text-sm text-muted-foreground tabular-nums">
                                            Taux observé :{' '}
                                            {operation.observed_rate}
                                        </p>
                                    )}

                                    {operation.notes && (
                                        <p className="text-sm text-muted-foreground">
                                            {operation.notes}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}

                    {canRecord && (
                        <div className="space-y-4 border-t pt-4">
                            <h3 className="font-medium">
                                Enregistrer une opération
                            </h3>
                            <OperationForm
                                cycleId={cycle.id}
                                operationTypes={operationTypes}
                                inputTypes={inputTypes}
                                units={units}
                            />
                        </div>
                    )}
                </section>

                <section className="max-w-2xl space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="flex items-center gap-2 font-heading text-lg font-semibold">
                        <Wheat className="size-5" />
                        Récoltes
                    </h2>

                    {harvests.length === 0 ? (
                        <EmptyState
                            icon={Wheat}
                            title="Aucune récolte"
                            description="Les indicateurs du cycle se calculent à partir de la première récolte."
                        />
                    ) : (
                        <ol className="space-y-3" data-test="harvest-list">
                            {harvests.map((harvest) => (
                                <li
                                    key={harvest.id}
                                    className="space-y-1 rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <p className="font-medium tabular-nums">
                                            {harvest.quantity_kg} kg
                                        </p>
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {harvest.date_label}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {harvest.unit_price
                                            ? `${harvest.unit_price} FCFA/kg`
                                            : 'Prix non renseigné'}
                                        {harvest.quality_label &&
                                            ` · ${harvest.quality_label}`}
                                        {harvest.post_harvest_losses_kg &&
                                            ` · ${harvest.post_harvest_losses_kg} kg de pertes`}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}

                    {canRecord && (
                        <div className="space-y-4 border-t pt-4">
                            <h3 className="font-medium">
                                Enregistrer une récolte
                            </h3>
                            <HarvestForm
                                cycleId={cycle.id}
                                qualities={qualities}
                            />
                        </div>
                    )}
                </section>

                {cycle.is_open && (
                    <section className="max-w-2xl space-y-4 rounded-xl border bg-card p-4 md:p-6">
                        <h2 className="font-heading text-lg font-semibold">
                            Fin du cycle
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Clôturez le cycle une fois la récolte terminée, ou
                            abandonnez-le s'il n'est pas mené à son terme.
                        </p>

                        <div className="flex flex-wrap gap-2">
                            <Form {...CropCycleController.close.form(cycle.id)}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        className="h-12"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Clôturer le cycle
                                    </Button>
                                )}
                            </Form>

                            <Form
                                {...CropCycleController.abandon.form(cycle.id)}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="h-12"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Abandonner le cycle
                                    </Button>
                                )}
                            </Form>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

CycleShow.layout = {
    breadcrumbs: [{ title: 'Cycles culturaux', href: index() }],
};
