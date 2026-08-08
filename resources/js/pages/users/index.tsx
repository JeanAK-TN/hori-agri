import { Form, Head, usePage } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import PasswordInput from '@/components/password-input';
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
import { index as usersIndex } from '@/routes/users';
import type { Auth, Paginated } from '@/types';

type Account = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    role: string;
    role_label: string;
    cooperative: { id: number; name: string } | null;
};

type RoleOption = { value: string; label: string };

type Props = {
    users: Paginated<Account>;
    assignableRoles: RoleOption[];
    cooperatives: { id: number; name: string }[] | null;
};

type FieldsProps = {
    errors: Record<string, string>;
    assignableRoles: RoleOption[];
    cooperatives: Props['cooperatives'];
    account?: Account;
};

function AccountFields({
    errors,
    assignableRoles,
    cooperatives,
    account,
}: FieldsProps) {
    const isEdit = account !== undefined;

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Nom complet</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autoFocus
                    className="h-12"
                    defaultValue={account?.name}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Numéro de téléphone</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    inputMode="tel"
                    required
                    className="h-12"
                    placeholder="90 00 00 00"
                    defaultValue={account?.phone}
                />
                <p className="text-sm text-muted-foreground">
                    Il sert d'identifiant de connexion.
                </p>
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Adresse e-mail (facultative)</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    className="h-12"
                    placeholder="adresse@exemple.com"
                    defaultValue={account?.email ?? ''}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role">Rôle</Label>
                <Select
                    name="role"
                    defaultValue={account?.role ?? assignableRoles[0]?.value}
                >
                    <SelectTrigger id="role" className="w-full">
                        <SelectValue placeholder="Choisir un rôle" />
                    </SelectTrigger>
                    <SelectContent>
                        {assignableRoles.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.role} />
            </div>

            {!isEdit && cooperatives !== null && (
                <div className="grid gap-2">
                    <Label htmlFor="cooperative_id">Coopérative</Label>
                    <Select name="cooperative_id">
                        <SelectTrigger id="cooperative_id" className="w-full">
                            <SelectValue placeholder="Choisir une coopérative" />
                        </SelectTrigger>
                        <SelectContent>
                            {cooperatives.map((cooperative) => (
                                <SelectItem
                                    key={cooperative.id}
                                    value={String(cooperative.id)}
                                >
                                    {cooperative.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.cooperative_id} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="password">
                    {isEdit
                        ? 'Nouveau mot de passe (facultatif)'
                        : 'Mot de passe'}
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required={!isEdit}
                    autoComplete="new-password"
                    className="h-12"
                />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">
                    Confirmer le mot de passe
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required={!isEdit}
                    autoComplete="new-password"
                    className="h-12"
                />
                <InputError message={errors.password_confirmation} />
            </div>
        </div>
    );
}

function CreateDialog({
    assignableRoles,
    cooperatives,
}: Omit<FieldsProps, 'errors' | 'account'>) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-12">
                    <Plus className="size-4" />
                    Nouveau compte
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nouveau compte</DialogTitle>
                    <DialogDescription>
                        Le titulaire se connectera avec son numéro de téléphone
                        et le mot de passe que vous définissez ici.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...UserController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <AccountFields
                                errors={errors}
                                assignableRoles={assignableRoles}
                                cooperatives={cooperatives}
                            />
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Créer le compte
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
    account,
    assignableRoles,
    cooperatives,
}: Omit<FieldsProps, 'errors'> & { account: Account }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-12"
                    aria-label={`Modifier le compte de ${account.name}`}
                >
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Modifier le compte</DialogTitle>
                    <DialogDescription>{account.name}</DialogDescription>
                </DialogHeader>

                <Form
                    {...UserController.update.form(account.id)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <AccountFields
                                errors={errors}
                                assignableRoles={assignableRoles}
                                cooperatives={cooperatives}
                                account={account}
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

export default function UsersIndex({
    users,
    assignableRoles,
    cooperatives,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const showCooperative = auth.user.role === 'super_admin';
    const canCreate = assignableRoles.length > 0;

    return (
        <>
            <Head title="Comptes" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Comptes"
                    description={
                        showCooperative
                            ? 'Tous les comptes de la solution.'
                            : 'Les comptes de votre coopérative.'
                    }
                >
                    {canCreate && (
                        <CreateDialog
                            assignableRoles={assignableRoles}
                            cooperatives={cooperatives}
                        />
                    )}
                </PageHeader>

                {users.total === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Aucun compte"
                        description="Créez le premier compte pour donner accès à l'application."
                    />
                ) : (
                    <>
                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {users.data.map((account) => (
                                <li
                                    key={account.id}
                                    className="space-y-3 rounded-xl border bg-card p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-heading font-semibold">
                                                {account.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground tabular-nums">
                                                {account.phone}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            {account.role_label}
                                        </Badge>
                                    </div>

                                    {showCooperative && account.cooperative && (
                                        <p className="text-sm text-muted-foreground">
                                            {account.cooperative.name}
                                        </p>
                                    )}

                                    <EditDialog
                                        account={account}
                                        assignableRoles={assignableRoles}
                                        cooperatives={cooperatives}
                                    />
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Nom
                                        </TableHead>
                                        <TableHead>Téléphone</TableHead>
                                        <TableHead>Adresse e-mail</TableHead>
                                        <TableHead>Rôle</TableHead>
                                        {showCooperative && (
                                            <TableHead>Coopérative</TableHead>
                                        )}
                                        <TableHead className="px-4 text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((account) => (
                                        <TableRow key={account.id}>
                                            <TableCell className="px-4 font-medium">
                                                {account.name}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {account.phone}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {account.email ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {account.role_label}
                                                </Badge>
                                            </TableCell>
                                            {showCooperative && (
                                                <TableCell className="text-muted-foreground">
                                                    {account.cooperative
                                                        ?.name ?? '—'}
                                                </TableCell>
                                            )}
                                            <TableCell className="px-4">
                                                <div className="flex justify-end">
                                                    <EditDialog
                                                        account={account}
                                                        assignableRoles={
                                                            assignableRoles
                                                        }
                                                        cooperatives={
                                                            cooperatives
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={users} label="comptes" />
                    </>
                )}
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Comptes',
            href: usersIndex(),
        },
    ],
};
