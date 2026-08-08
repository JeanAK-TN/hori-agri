import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Phone } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/farmers';

type Farmer = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    gender_label: string | null;
    birth_year: number | null;
    experience_years: number | null;
    village: string;
    phone: string | null;
    account: { id: number; name: string; phone: string } | null;
};

type Props = {
    farmer: Farmer;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

export default function FarmerShow({ farmer }: Props) {
    return (
        <>
            <Head title={farmer.full_name} />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={farmer.full_name}
                    description={`Producteur à ${farmer.village}`}
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>
                    </Button>
                    <Button asChild className="h-12">
                        <Link href={edit(farmer.id)}>
                            <Pencil className="size-4" />
                            Modifier
                        </Link>
                    </Button>
                </PageHeader>

                <section className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="font-heading text-lg font-semibold">
                        Identité
                    </h2>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Village" value={farmer.village} />
                        <Detail
                            label="Sexe"
                            value={farmer.gender_label ?? 'Non renseigné'}
                        />
                        <Detail
                            label="Année de naissance"
                            value={
                                farmer.birth_year === null
                                    ? 'Non renseignée'
                                    : String(farmer.birth_year)
                            }
                        />
                        <Detail
                            label="Années d'expérience"
                            value={
                                farmer.experience_years === null
                                    ? 'Non renseignées'
                                    : `${farmer.experience_years} ans`
                            }
                        />
                    </dl>

                    {farmer.phone && (
                        <Button
                            asChild
                            variant="outline"
                            className="h-12 w-full sm:w-auto"
                        >
                            <a href={`tel:${farmer.phone}`}>
                                <Phone className="size-4" />
                                Appeler le {farmer.phone}
                            </a>
                        </Button>
                    )}
                </section>

                <section className="max-w-2xl space-y-3 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="font-heading text-lg font-semibold">
                        Compte de consultation
                    </h2>

                    {farmer.account === null ? (
                        <p className="text-sm text-muted-foreground">
                            Ce producteur ne consulte pas lui-même
                            l'application. Rattachez un compte agriculteur
                            depuis l'écran de modification.
                        </p>
                    ) : (
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge variant="secondary">Compte rattaché</Badge>
                            <span className="font-medium">
                                {farmer.account.name}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {farmer.account.phone}
                            </span>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

FarmerShow.layout = {
    breadcrumbs: [{ title: 'Producteurs', href: index() }],
};
