import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PlotController from '@/actions/App/Http/Controllers/PlotController';
import PageHeader from '@/components/page-header';
import PlotFormFields from '@/components/plot-form-fields';
import type {
    FarmerOption,
    Option,
    PlotFormValues,
} from '@/components/plot-form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/plots';

type Plot = PlotFormValues & { id: number };

type Props = {
    plot: Plot;
    soilTypes: Option[];
    farmers: FarmerOption[];
};

export default function PlotEdit({ plot, soilTypes, farmers }: Props) {
    return (
        <>
            <Head title={`Modifier ${plot.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Modifier la parcelle"
                    description={plot.name}
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={show(plot.id)}>
                            <ArrowLeft className="size-4" />
                            Retour à la fiche
                        </Link>
                    </Button>
                </PageHeader>

                <Form
                    {...PlotController.update.form(plot.id)}
                    className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlotFormFields
                                errors={errors}
                                soilTypes={soilTypes}
                                farmers={farmers}
                                plot={plot}
                            />

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Enregistrer les modifications
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-12"
                                >
                                    <Link href={show(plot.id)}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PlotEdit.layout = {
    breadcrumbs: [{ title: 'Parcelles', href: index() }],
};
