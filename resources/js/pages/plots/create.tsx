import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PlotController from '@/actions/App/Http/Controllers/PlotController';
import PageHeader from '@/components/page-header';
import PlotFormFields from '@/components/plot-form-fields';
import type { FarmerOption, Option } from '@/components/plot-form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/plots';

type Props = {
    soilTypes: Option[];
    farmers: FarmerOption[];
    selectedFarmerId: number | null;
};

export default function PlotCreate({
    soilTypes,
    farmers,
    selectedFarmerId,
}: Props) {
    return (
        <>
            <Head title="Nouvelle parcelle" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Nouvelle parcelle"
                    description="Rattachez la parcelle à un producteur de votre coopérative."
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour à la liste
                        </Link>
                    </Button>
                </PageHeader>

                <Form
                    {...PlotController.store.form()}
                    className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlotFormFields
                                errors={errors}
                                soilTypes={soilTypes}
                                farmers={farmers}
                                defaultFarmerId={selectedFarmerId}
                            />

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Enregistrer la parcelle
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-12"
                                >
                                    <Link href={index()}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PlotCreate.layout = {
    breadcrumbs: [
        { title: 'Parcelles', href: index() },
        { title: 'Nouvelle parcelle', href: index() },
    ],
};
