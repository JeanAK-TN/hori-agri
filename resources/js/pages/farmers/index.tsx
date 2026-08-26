import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Sprout, UserRound } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/farmers';
import type { Paginated } from '@/types';

type FarmerRow = {
    id: number;
    first_name: string;
    last_name: string;
    village: string;
    phone: string | null;
    experience_years: number | null;
    has_account: boolean;
};

type Props = {
    farmers: Paginated<FarmerRow>;
    filters: { recherche: string };
};

export default function FarmersIndex({ farmers, filters }: Props) {
    const submitSearch = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const term = new FormData(event.currentTarget).get('recherche');

        router.get(
            index().url,
            { recherche: String(term ?? '') },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Producteurs" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Producteurs"
                    description="Les producteurs suivis par votre exploitation."
                >
                    <Button asChild className="h-12">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nouveau producteur
                        </Link>
                    </Button>
                </PageHeader>

                <form onSubmit={submitSearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <Label htmlFor="recherche" className="sr-only">
                            Rechercher un producteur
                        </Label>
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="recherche"
                            name="recherche"
                            type="search"
                            className="h-12 pl-9"
                            placeholder="Nom, village ou téléphone"
                            defaultValue={filters.recherche}
                        />
                    </div>
                    <Button type="submit" variant="outline" className="h-12">
                        Rechercher
                    </Button>
                </form>

                {farmers.total === 0 ? (
                    <EmptyState
                        icon={Sprout}
                        title={
                            filters.recherche
                                ? 'Aucun producteur ne correspond'
                                : 'Aucun producteur'
                        }
                        description={
                            filters.recherche
                                ? 'Essayez un autre nom, village ou numéro.'
                                : 'Enregistrez le premier producteur de votre exploitation.'
                        }
                    >
                        {!filters.recherche && (
                            <Button asChild className="h-12">
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Nouveau producteur
                                </Link>
                            </Button>
                        )}
                    </EmptyState>
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {farmers.data.map((farmer) => (
                                <li key={farmer.id}>
                                    <Link
                                        href={show(farmer.id)}
                                        className="flex min-h-14 items-center justify-between gap-3 rounded-xl border bg-card p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {farmer.first_name}{' '}
                                                {farmer.last_name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {farmer.village}
                                                {farmer.phone &&
                                                    ` · ${farmer.phone}`}
                                            </p>
                                        </div>
                                        {farmer.has_account && (
                                            <Badge variant="secondary">
                                                <UserRound className="size-3" />
                                                Compte
                                            </Badge>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Producteur
                                        </TableHead>
                                        <TableHead>Village</TableHead>
                                        <TableHead>Téléphone</TableHead>
                                        <TableHead className="text-right">
                                            Expérience
                                        </TableHead>
                                        <TableHead>Compte</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {farmers.data.map((farmer) => (
                                        <TableRow
                                            key={farmer.id}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                router.visit(show(farmer.id))
                                            }
                                        >
                                            <TableCell className="px-4 font-medium">
                                                <Link
                                                    href={show(farmer.id)}
                                                    className="hover:underline"
                                                >
                                                    {farmer.first_name}{' '}
                                                    {farmer.last_name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {farmer.village}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground tabular-nums">
                                                {farmer.phone ??
                                                    'Non renseigné'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {farmer.experience_years ===
                                                null
                                                    ? 'Non renseignée'
                                                    : `${farmer.experience_years} ans`}
                                            </TableCell>
                                            <TableCell>
                                                {farmer.has_account ? (
                                                    <Badge variant="secondary">
                                                        Oui
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Non
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav
                            paginator={farmers}
                            label="producteurs"
                        />
                    </>
                )}
            </div>
        </>
    );
}

FarmersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Producteurs',
            href: index(),
        },
    ],
};
