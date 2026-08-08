import { Head, Link, router } from '@inertiajs/react';
import { Plus, Sprout } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import StatusBadge from '@/components/status-badge';
import type { CycleStatusValue } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/cycles';
import type { Paginated } from '@/types';

type CycleRow = {
    id: number;
    crop: string;
    campaign: string;
    plot: string;
    farmer: string;
    village: string;
    sowing_date_label: string;
    status: CycleStatusValue;
    status_label: string;
};

type Option = { id: number; label: string };

type Props = {
    cycles: Paginated<CycleRow>;
    filters: { campagne: number | null; culture: number | null };
    campaigns: Option[];
    crops: Option[];
};

const ALL = 'toutes';

export default function CyclesIndex({
    cycles,
    filters,
    campaigns,
    crops,
}: Props) {
    const applyFilters = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        router.get(
            index().url,
            {
                campagne: merged.campagne ?? undefined,
                culture: merged.culture ?? undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const hasFilter = filters.campagne !== null || filters.culture !== null;

    return (
        <>
            <Head title="Cycles culturaux" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Cycles culturaux"
                    description="Une culture, une parcelle, une campagne."
                >
                    <Button asChild className="h-12">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Ouvrir un cycle
                        </Link>
                    </Button>
                </PageHeader>

                <div className="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
                    <div className="grid gap-2">
                        <Label htmlFor="filtre-campagne">Campagne</Label>
                        <Select
                            value={
                                filters.campagne === null
                                    ? ALL
                                    : String(filters.campagne)
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    campagne:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                id="filtre-campagne"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Toutes les campagnes
                                </SelectItem>
                                {campaigns.map((campaign) => (
                                    <SelectItem
                                        key={campaign.id}
                                        value={String(campaign.id)}
                                    >
                                        {campaign.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filtre-culture">Culture</Label>
                        <Select
                            value={
                                filters.culture === null
                                    ? ALL
                                    : String(filters.culture)
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    culture:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                id="filtre-culture"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Toutes les cultures
                                </SelectItem>
                                {crops.map((crop) => (
                                    <SelectItem
                                        key={crop.id}
                                        value={String(crop.id)}
                                    >
                                        {crop.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {cycles.total === 0 ? (
                    <EmptyState
                        icon={Sprout}
                        title={
                            hasFilter
                                ? 'Aucun cycle ne correspond'
                                : 'Aucun cycle cultural'
                        }
                        description={
                            hasFilter
                                ? 'Changez de campagne ou de culture.'
                                : 'Ouvrez un cycle sur une parcelle pour commencer le suivi.'
                        }
                    >
                        {!hasFilter && (
                            <Button asChild className="h-12">
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Ouvrir un cycle
                                </Link>
                            </Button>
                        )}
                    </EmptyState>
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {cycles.data.map((cycle) => (
                                <li key={cycle.id}>
                                    <Link
                                        href={show(cycle.id)}
                                        className="flex min-h-14 items-center justify-between gap-3 rounded-xl border bg-card p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {cycle.crop} · {cycle.plot}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {cycle.farmer} ·{' '}
                                                {cycle.campaign}
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

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Culture
                                        </TableHead>
                                        <TableHead>Parcelle</TableHead>
                                        <TableHead>Producteur</TableHead>
                                        <TableHead>Campagne</TableHead>
                                        <TableHead>Semis</TableHead>
                                        <TableHead className="px-4">
                                            État
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cycles.data.map((cycle) => (
                                        <TableRow key={cycle.id}>
                                            <TableCell className="px-4 font-medium">
                                                <Link
                                                    href={show(cycle.id)}
                                                    className="hover:underline"
                                                >
                                                    {cycle.crop}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{cycle.plot}</TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {cycle.farmer}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {cycle.campaign}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {cycle.sowing_date_label}
                                            </TableCell>
                                            <TableCell className="px-4">
                                                <StatusBadge
                                                    status={cycle.status}
                                                    label={cycle.status_label}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={cycles} label="cycles" />
                    </>
                )}
            </div>
        </>
    );
}

CyclesIndex.layout = {
    breadcrumbs: [{ title: 'Cycles culturaux', href: index() }],
};
