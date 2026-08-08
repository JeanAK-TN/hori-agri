import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import FarmerController from '@/actions/App/Http/Controllers/FarmerController';
import FarmerFormFields, {
    normalizeFarmerPayload,
} from '@/components/farmer-form-fields';
import type { LinkableAccount, Option } from '@/components/farmer-form-fields';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/farmers';

type Props = {
    genders: Option[];
    availableAccounts: LinkableAccount[];
};

export default function FarmerCreate({ genders, availableAccounts }: Props) {
    return (
        <>
            <Head title="Nouveau producteur" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Nouveau producteur"
                    description="Enregistrez la fiche d'un producteur de votre coopérative."
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour à la liste
                        </Link>
                    </Button>
                </PageHeader>

                <Form
                    {...FarmerController.store.form()}
                    transform={normalizeFarmerPayload}
                    className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FarmerFormFields
                                errors={errors}
                                genders={genders}
                                availableAccounts={availableAccounts}
                            />

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Enregistrer le producteur
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

FarmerCreate.layout = {
    breadcrumbs: [
        { title: 'Producteurs', href: index() },
        { title: 'Nouveau producteur', href: index() },
    ],
};
