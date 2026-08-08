import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, CircleSlash, FileUp, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/imports';

type LineStatus = 'creee' | 'ignoree' | 'rejetee';

type ReportLine = {
    line: number;
    status: LineStatus;
    reason: string | null;
};

type Report = {
    lines: ReportLine[];
    created: number;
    ignored: number;
    rejected: number;
    total: number;
};

type Props = {
    colonnes: string[];
    separateur: string;
    rapport: Report | null;
};

const STATUS_LABELS: Record<LineStatus, string> = {
    creee: 'Enregistrée',
    ignoree: 'Déjà présente',
    rejetee: 'Rejetée',
};

const STATUS_TONES: Record<LineStatus, string> = {
    creee: 'bg-severity-low text-severity-low-foreground',
    ignoree: 'bg-muted text-muted-foreground',
    rejetee: 'bg-severity-high text-severity-high-foreground',
};

function Tally({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof CheckCircle2;
    label: string;
    value: number;
    tone: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    tone,
                )}
            >
                <Icon className="size-5" />
            </span>
            <div>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
                <p className="text-sm text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

export default function ImportsIndex({ colonnes, separateur, rapport }: Props) {
    const { setData, post, processing, errors, progress } = useForm<{
        fichier: File | null;
    }>({ fichier: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        post(store().url, { forceFormData: true });
    };

    return (
        <>
            <Head title="Import de données" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Import de données"
                    description="Déposez un fichier d'opérations et de récoltes. Chaque ligne est traitée séparément."
                />

                <section className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="fichier">Fichier CSV</Label>
                            <Input
                                id="fichier"
                                name="fichier"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                required
                                className="h-12 py-2.5"
                                onChange={(event) =>
                                    setData(
                                        'fichier',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={errors.fichier} />
                        </div>

                        {progress && (
                            <progress
                                value={progress.percentage}
                                max="100"
                                className="w-full"
                            >
                                {progress.percentage}%
                            </progress>
                        )}

                        <Button
                            type="submit"
                            className="h-12"
                            disabled={processing}
                            data-test="submit-import"
                        >
                            {processing && <Spinner />}
                            <FileUp className="size-4" />
                            Déposer le fichier
                        </Button>
                    </form>

                    <div className="space-y-2 border-t pt-4">
                        <h2 className="font-heading font-semibold">
                            Format attendu
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Séparateur « {separateur} », encodage UTF-8. La
                            colonne <code>ligne_type</code> vaut{' '}
                            <code>operation</code> ou <code>recolte</code>. Les
                            dates s'écrivent JJ/MM/AAAA. Le producteur est
                            retrouvé par son téléphone, la parcelle par son nom,
                            et le cycle doit déjà être ouvert.
                        </p>
                        <p className="text-sm break-words text-muted-foreground">
                            <code>{colonnes.join(separateur)}</code>
                        </p>
                    </div>
                </section>

                {rapport && (
                    <section className="space-y-4" data-test="import-report">
                        <h2 className="font-heading text-lg font-semibold">
                            Rapport du dernier dépôt
                        </h2>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <Tally
                                icon={CheckCircle2}
                                label="ligne(s) enregistrée(s)"
                                value={rapport.created}
                                tone="bg-severity-low text-severity-low-foreground"
                            />
                            <Tally
                                icon={CircleSlash}
                                label="ligne(s) déjà présente(s)"
                                value={rapport.ignored}
                                tone="bg-muted text-muted-foreground"
                            />
                            <Tally
                                icon={XCircle}
                                label="ligne(s) rejetée(s)"
                                value={rapport.rejected}
                                tone="bg-severity-high text-severity-high-foreground"
                            />
                        </div>

                        {/* Cartes empilées sous 640 px (SPEC section 11.4) */}
                        <ul className="space-y-3 sm:hidden">
                            {rapport.lines.map((line) => (
                                <li
                                    key={line.line}
                                    className="space-y-2 rounded-xl border bg-card p-4"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="font-medium tabular-nums">
                                            Ligne {line.line}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-md px-2 py-0.5 text-xs font-medium',
                                                STATUS_TONES[line.status],
                                            )}
                                        >
                                            {STATUS_LABELS[line.status]}
                                        </span>
                                    </div>
                                    {line.reason && (
                                        <p className="text-sm text-muted-foreground">
                                            {line.reason}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-hidden rounded-xl border bg-card sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-4">
                                            Ligne
                                        </TableHead>
                                        <TableHead>État</TableHead>
                                        <TableHead className="px-4">
                                            Motif
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rapport.lines.map((line) => (
                                        <TableRow key={line.line}>
                                            <TableCell className="px-4 font-medium tabular-nums">
                                                {line.line}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={cn(
                                                        'rounded-md px-2 py-0.5 text-xs font-medium',
                                                        STATUS_TONES[
                                                            line.status
                                                        ],
                                                    )}
                                                >
                                                    {STATUS_LABELS[line.status]}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-4 text-muted-foreground">
                                                {line.reason ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Import de données', href: index() }],
};
