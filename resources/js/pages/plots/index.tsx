import { Head, Link, router } from '@inertiajs/react';
import { LandPlot, Plus, Search } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
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
import { create, index, show } from '@/routes/plots';
import type { Paginated } from '@/types';

type PlotRow = {
    id: number;
    name: string;
    area_ha: string;
    soil_type_label: string;
    farmer: { id: number; full_name: string; village: string };
};

type Props = {
    plots: Paginated<PlotRow>;
    filters: { recherche: string };
};

export default function PlotsIndex({ plots, filters }: Props) {
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
            <Head title="Parcelles" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Parcelles"
                    description="Les parcelles suivies par votre exploitation."
                >
                    <Button asChild className="h-12">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nouvelle parcelle
                        </Link>
                    </Button>
                </PageHeader>

                <form onSubmit={submitSearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <Label htmlFor="recherche" className="sr-only">
                            Rechercher une parcelle
                        </Label>
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="recherche"
                            name="recherche"
                            type="search"
                            className="h-12 pl-9"
                            placeholder="Parcelle, producteur ou village"
                            defaultValue={filters.recherche}
                        />
                    </div>
                    <Button type="submit" variant="outline" className="h-12">
                        Rechercher
                    </Button>
                </form>

                {plots.total === 0 ? (
                    <EmptyState
                        icon={LandPlot}
                        title={
                            filters.recherche
                                ? 'Aucune parcelle ne correspond'
                                : 'Aucune parcelle'
                        }
                        description={
                            filters.recherche
                                ? 'Essayez un autre nom de parcelle, de producteur ou de village.'
                                : 'Enregistrez la première parcelle de votre exploitation.'
                        }
                    >
                        {!filters.recherche && (
                            <Button asChild className="h-12">
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Nouvelle parcelle
                                </Link>
                            </Button>
                        )}
                    </EmptyState>
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {plots.data.map((plot) => (
                                <li key={plot.id}>
                                    <Link
                                        href={show(plot.id)}
                                        className="flex min-h-14 items-center justify-between gap-3 rounded-xl border bg-card p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {plot.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {plot.farmer.full_name} ·{' '}
                                                {plot.farmer.village}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm font-medium tabular-nums">
                                            {plot.area_ha} ha
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Parcelle
                                        </TableHead>
                                        <TableHead>Producteur</TableHead>
                                        <TableHead>Village</TableHead>
                                        <TableHead>Type de sol</TableHead>
                                        <TableHead className="px-4 text-right">
                                            Surface
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {plots.data.map((plot) => (
                                        <TableRow key={plot.id}>
                                            <TableCell className="px-4 font-medium">
                                                <Link
                                                    href={show(plot.id)}
                                                    className="hover:underline"
                                                >
                                                    {plot.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {plot.farmer.full_name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {plot.farmer.village}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {plot.soil_type_label}
                                            </TableCell>
                                            <TableCell className="px-4 text-right tabular-nums">
                                                {plot.area_ha} ha
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={plots} label="parcelles" />
                    </>
                )}
            </div>
        </>
    );
}

PlotsIndex.layout = {
    breadcrumbs: [{ title: 'Parcelles', href: index() }],
};
