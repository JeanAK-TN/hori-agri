import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList } from 'lucide-react';
import CropCycleController from '@/actions/App/Http/Controllers/CropCycleController';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import type { CycleStatusValue } from '@/components/status-badge';
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
    density: number | null;
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

type Props = {
    cycle: Cycle;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

export default function CycleShow({ cycle }: Props) {
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
                            label="Densité"
                            value={
                                cycle.density === null
                                    ? 'Non renseignée'
                                    : `${cycle.density} plants/ha`
                            }
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

                <section className="max-w-2xl space-y-3 rounded-xl border border-dashed bg-card p-4 md:p-6">
                    <h2 className="flex items-center gap-2 font-heading text-lg font-semibold">
                        <ClipboardList className="size-5" />
                        Itinéraire technique
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        La saisie des opérations et des récoltes arrive avec le
                        module suivant.
                    </p>
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
