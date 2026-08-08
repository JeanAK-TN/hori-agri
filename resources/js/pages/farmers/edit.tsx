import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import FarmerController from '@/actions/App/Http/Controllers/FarmerController';
import FarmerFormFields, {
    normalizeFarmerPayload,
} from '@/components/farmer-form-fields';
import type {
    FarmerFormValues,
    LinkableAccount,
    Option,
} from '@/components/farmer-form-fields';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/farmers';

type Farmer = FarmerFormValues & { id: number; full_name: string };

type Props = {
    farmer: Farmer;
    genders: Option[];
    availableAccounts: LinkableAccount[];
};

export default function FarmerEdit({
    farmer,
    genders,
    availableAccounts,
}: Props) {
    return (
        <>
            <Head title={`Modifier ${farmer.full_name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Modifier le producteur"
                    description={farmer.full_name}
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={show(farmer.id)}>
                            <ArrowLeft className="size-4" />
                            Retour à la fiche
                        </Link>
                    </Button>
                </PageHeader>

                <Form
                    {...FarmerController.update.form(farmer.id)}
                    transform={normalizeFarmerPayload}
                    className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FarmerFormFields
                                errors={errors}
                                genders={genders}
                                availableAccounts={availableAccounts}
                                farmer={farmer}
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
                                    <Link href={show(farmer.id)}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

FarmerEdit.layout = {
    breadcrumbs: [{ title: 'Producteurs', href: index() }],
};
