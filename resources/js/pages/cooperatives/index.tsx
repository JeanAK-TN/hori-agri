import { Form, Head } from '@inertiajs/react';
import { Building2, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CooperativeController from '@/actions/App/Http/Controllers/CooperativeController';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as cooperativesIndex } from '@/routes/cooperatives';
import type { Paginated } from '@/types';

type Cooperative = {
    id: number;
    name: string;
    region: string;
    prefecture: string;
    contact: string | null;
    users_count: number;
    suspended: boolean;
};

type Props = {
    cooperatives: Paginated<Cooperative>;
};

function StatusBadge({ suspended }: { suspended: boolean }) {
    return suspended ? (
        <Badge className="bg-severity-high text-severity-high-foreground">
            Suspendue
        </Badge>
    ) : (
        <Badge className="bg-severity-low text-severity-low-foreground">
            Active
        </Badge>
    );
}

function CooperativeFields({
    cooperative,
    errors,
}: {
    cooperative?: Cooperative;
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Nom de la coopérative</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autoFocus
                    className="h-12"
                    defaultValue={cooperative?.name}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="region">Région</Label>
                    <Input
                        id="region"
                        name="region"
                        required
                        className="h-12"
                        defaultValue={cooperative?.region}
                    />
                    <InputError message={errors.region} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="prefecture">Préfecture</Label>
                    <Input
                        id="prefecture"
                        name="prefecture"
                        required
                        className="h-12"
                        defaultValue={cooperative?.prefecture}
                    />
                    <InputError message={errors.prefecture} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="contact">Contact du responsable</Label>
                <Input
                    id="contact"
                    name="contact"
                    className="h-12"
                    placeholder="Téléphone ou adresse e-mail"
                    defaultValue={cooperative?.contact ?? ''}
                />
                <InputError message={errors.contact} />
            </div>
        </div>
    );
}

function CreateDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-12">
                    <Plus className="size-4" />
                    Nouvelle coopérative
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nouvelle coopérative</DialogTitle>
                    <DialogDescription>
                        Renseignez la coopérative, puis créez le compte de son
                        administrateur depuis l'écran des comptes.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CooperativeController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CooperativeFields errors={errors} />
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Créer la coopérative
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({ cooperative }: { cooperative: Cooperative }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-12"
                    aria-label={`Modifier ${cooperative.name}`}
                >
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la coopérative</DialogTitle>
                    <DialogDescription>{cooperative.name}</DialogDescription>
                </DialogHeader>

                <Form
                    {...CooperativeController.update.form(cooperative.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CooperativeFields
                                cooperative={cooperative}
                                errors={errors}
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

function SuspensionButton({ cooperative }: { cooperative: Cooperative }) {
    const action = cooperative.suspended
        ? CooperativeController.reactivate.form(cooperative.id)
        : CooperativeController.suspend.form(cooperative.id);

    return (
        <Form {...action}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="outline"
                    className="h-12"
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    {cooperative.suspended ? 'Réactiver' : 'Suspendre'}
                </Button>
            )}
        </Form>
    );
}

export default function CooperativesIndex({ cooperatives }: Props) {
    return (
        <>
            <Head title="Coopératives" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Coopératives"
                    description="Créez les coopératives de la solution et suspendez celles qui ne sont plus actives."
                >
                    <CreateDialog />
                </PageHeader>

                {cooperatives.total === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="Aucune coopérative"
                        description="Créez la première coopérative pour commencer à ouvrir des comptes."
                    />
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {cooperatives.data.map((cooperative) => (
                                <li
                                    key={cooperative.id}
                                    className="space-y-3 rounded-xl border bg-card p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {cooperative.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {cooperative.prefecture},{' '}
                                                {cooperative.region}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            suspended={cooperative.suspended}
                                        />
                                    </div>

                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {cooperative.users_count} compte
                                        {cooperative.users_count > 1 ? 's' : ''}
                                        {cooperative.contact &&
                                            ` · ${cooperative.contact}`}
                                    </p>

                                    <div className="flex items-center gap-2">
                                        <SuspensionButton
                                            cooperative={cooperative}
                                        />
                                        <EditDialog cooperative={cooperative} />
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Coopérative
                                        </TableHead>
                                        <TableHead>Préfecture</TableHead>
                                        <TableHead>Région</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead className="text-right">
                                            Comptes
                                        </TableHead>
                                        <TableHead>État</TableHead>
                                        <TableHead className="px-4 text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cooperatives.data.map((cooperative) => (
                                        <TableRow key={cooperative.id}>
                                            <TableCell className="px-4 font-medium">
                                                {cooperative.name}
                                            </TableCell>
                                            <TableCell>
                                                {cooperative.prefecture}
                                            </TableCell>
                                            <TableCell>
                                                {cooperative.region}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {cooperative.contact ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {cooperative.users_count}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    suspended={
                                                        cooperative.suspended
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="px-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    <SuspensionButton
                                                        cooperative={
                                                            cooperative
                                                        }
                                                    />
                                                    <EditDialog
                                                        cooperative={
                                                            cooperative
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav
                            paginator={cooperatives}
                            label="coopératives"
                        />
                    </>
                )}
            </div>
        </>
    );
}

CooperativesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Coopératives',
            href: cooperativesIndex(),
        },
    ],
};
