import { Form, Head, usePage } from '@inertiajs/react';
import { CalendarRange, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/campaigns';
import type { Auth, Paginated } from '@/types';

type Campaign = {
    id: number;
    label: string;
    start_date: string;
    end_date: string;
    start_date_label: string;
    end_date_label: string;
    season_type: string;
    season_type_label: string;
    cycles_count: number;
};

type Option = { value: string; label: string };

type Props = {
    campaigns: Paginated<Campaign>;
    seasonTypes: Option[];
};

function CampaignFields({
    errors,
    seasonTypes,
    campaign,
}: {
    errors: Record<string, string>;
    seasonTypes: Option[];
    campaign?: Campaign;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="label">Libellé</Label>
                <Input
                    id="label"
                    name="label"
                    required
                    autoFocus
                    className="h-12"
                    placeholder="2026 grande saison"
                    defaultValue={campaign?.label}
                />
                <InputError message={errors.label} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="start_date">Début</Label>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        required
                        className="h-12"
                        defaultValue={campaign?.start_date}
                    />
                    <InputError message={errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="end_date">Fin</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        required
                        className="h-12"
                        defaultValue={campaign?.end_date}
                    />
                    <InputError message={errors.end_date} />
                </div>
            </div>

            <p className="text-sm text-muted-foreground">
                Ces deux dates bornent toutes les dates saisies dans un cycle.
            </p>

            <div className="grid gap-2">
                <Label htmlFor="season_type">Saison</Label>
                <Select
                    name="season_type"
                    defaultValue={
                        campaign?.season_type ?? seasonTypes[0]?.value
                    }
                >
                    <SelectTrigger id="season_type" className="w-full">
                        <SelectValue placeholder="Choisir une saison" />
                    </SelectTrigger>
                    <SelectContent>
                        {seasonTypes.map((season) => (
                            <SelectItem key={season.value} value={season.value}>
                                {season.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.season_type} />
            </div>
        </div>
    );
}

function CreateDialog({ seasonTypes }: { seasonTypes: Option[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-12">
                    <Plus className="size-4" />
                    Nouvelle campagne
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nouvelle campagne</DialogTitle>
                    <DialogDescription>
                        Une campagne agricole borne les dates des cycles qu'elle
                        porte.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CampaignController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CampaignFields
                                errors={errors}
                                seasonTypes={seasonTypes}
                            />
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Créer la campagne
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({
    campaign,
    seasonTypes,
}: {
    campaign: Campaign;
    seasonTypes: Option[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-12"
                    aria-label={`Modifier ${campaign.label}`}
                >
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Modifier la campagne</DialogTitle>
                    <DialogDescription>{campaign.label}</DialogDescription>
                </DialogHeader>

                <Form
                    {...CampaignController.update.form(campaign.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CampaignFields
                                errors={errors}
                                seasonTypes={seasonTypes}
                                campaign={campaign}
                            />
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Enregistrer
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function CampaignsIndex({ campaigns, seasonTypes }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManage = auth.user.role === 'coop_admin';

    return (
        <>
            <Head title="Campagnes" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Campagnes"
                    description="Les campagnes agricoles de votre coopérative."
                >
                    {canManage && <CreateDialog seasonTypes={seasonTypes} />}
                </PageHeader>

                {campaigns.total === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Aucune campagne"
                        description="Ouvrez une campagne avant d'enregistrer des cycles culturaux."
                    />
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {campaigns.data.map((campaign) => (
                                <li
                                    key={campaign.id}
                                    className="space-y-3 rounded-xl border bg-card p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {campaign.label}
                                            </p>
                                            <p className="text-sm text-muted-foreground tabular-nums">
                                                du {campaign.start_date_label}{' '}
                                                au {campaign.end_date_label}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            {campaign.season_type_label}
                                        </Badge>
                                    </div>

                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {campaign.cycles_count} cycle
                                            {campaign.cycles_count > 1
                                                ? 's'
                                                : ''}
                                        </span>
                                        {canManage && (
                                            <EditDialog
                                                campaign={campaign}
                                                seasonTypes={seasonTypes}
                                            />
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Campagne
                                        </TableHead>
                                        <TableHead>Début</TableHead>
                                        <TableHead>Fin</TableHead>
                                        <TableHead>Saison</TableHead>
                                        <TableHead className="text-right">
                                            Cycles
                                        </TableHead>
                                        {canManage && (
                                            <TableHead className="px-4 text-right">
                                                Actions
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {campaigns.data.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell className="px-4 font-medium">
                                                {campaign.label}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {campaign.start_date_label}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {campaign.end_date_label}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {campaign.season_type_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {campaign.cycles_count}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="px-4">
                                                    <div className="flex justify-end">
                                                        <EditDialog
                                                            campaign={campaign}
                                                            seasonTypes={
                                                                seasonTypes
                                                            }
                                                        />
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav
                            paginator={campaigns}
                            label="campagnes"
                        />
                    </>
                )}
            </div>
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [{ title: 'Campagnes', href: index() }],
};
