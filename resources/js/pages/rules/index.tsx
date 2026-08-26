import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Head } from '@inertiajs/react';
import { BookOpen, Info, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import RuleController from '@/actions/App/Http/Controllers/RuleController';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index } from '@/routes/rules';

type ThresholdStatus = 'sourced' | 'imported' | 'convention';

type RuleRow = {
    id: number;
    code: string;
    crop: string | null;
    family: string;
    family_label: string;
    severity: string;
    severity_label: string;
    condition: Record<string, unknown>;
    condition_sentence: string;
    common_condition_sentence: string | null;
    message: string;
    action: string;
    estimated_gain: string | null;
    source: string;
    threshold_status: ThresholdStatus;
    threshold_status_label: string;
    enabled: boolean;
    is_override: boolean;
    is_overridden: boolean;
};

type Option = { value: string; label: string };

type Summary = {
    rules: number;
    sources: number;
    foreign_thresholds: number;
    convention_thresholds: number;
};

type Props = {
    rules: RuleRow[];
    summary: Summary;
    severities: Option[];
    fields: { value: string; label: string; unit: string }[];
    canOverride: boolean;
};

const SEVERITY_TONES: Record<string, string> = {
    high: 'bg-severity-high text-severity-high-foreground',
    medium: 'bg-severity-medium text-severity-medium-foreground',
    low: 'bg-severity-low text-severity-low-foreground',
};

/**
 * L'origine du seuil n'est jamais masquée : c'est ce qui distingue une
 * référence publiée d'une convention de travail (SPEC section 11).
 */
const THRESHOLD_TONES: Record<ThresholdStatus, string> = {
    sourced: 'bg-severity-low text-severity-low-foreground',
    imported: 'bg-secondary text-secondary-foreground',
    convention: 'bg-severity-medium text-severity-medium-foreground',
};

function parseCondition(raw: string): FormDataConvertible {
    try {
        return JSON.parse(raw) as FormDataConvertible;
    } catch {
        return raw;
    }
}

function OverrideDialog({
    rule,
    severities,
}: {
    rule: RuleRow;
    severities: Option[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="h-12"
                    data-test={`override-${rule.code}`}
                >
                    <SlidersHorizontal className="size-4" />
                    Surcharger
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Surcharger {rule.code}</DialogTitle>
                    <DialogDescription>
                        La règle commune n'est pas modifiée : une copie portant
                        le même code est enregistrée pour votre exploitation, et
                        c'est elle que le moteur retiendra.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...RuleController.update.form(rule.id)}
                    transform={(data) => ({
                        ...data,
                        // Un JSON mal formé part tel quel : le serveur le
                        // refuse et l'explique, plutôt que de casser ici.
                        condition: parseCondition(String(data.condition)),
                        enabled: data.enabled === 'on' || data.enabled === true,
                    })}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="condition">
                                    Condition (arbre JSON)
                                </Label>
                                <textarea
                                    id="condition"
                                    name="condition"
                                    rows={8}
                                    required
                                    spellCheck={false}
                                    defaultValue={JSON.stringify(
                                        rule.condition,
                                        null,
                                        2,
                                    )}
                                    className="w-full rounded-md border bg-transparent p-3 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.condition} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="severity">Sévérité</Label>
                                <Select
                                    name="severity"
                                    defaultValue={rule.severity}
                                >
                                    <SelectTrigger
                                        id="severity"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {severities.map((severity) => (
                                            <SelectItem
                                                key={severity.value}
                                                value={severity.value}
                                            >
                                                {severity.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.severity} />
                            </div>

                            <div className="flex min-h-12 items-center gap-3">
                                <Checkbox
                                    id="enabled"
                                    name="enabled"
                                    defaultChecked={rule.enabled}
                                />
                                <Label htmlFor="enabled">
                                    Cette règle s'évalue sur les cycles de mon
                                    exploitation
                                </Label>
                            </div>
                            <InputError message={errors.enabled} />

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                    data-test="save-override"
                                >
                                    {processing && <Spinner />}
                                    Enregistrer la surcharge
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function RulesIndex({
    rules,
    summary,
    severities,
    fields,
    canOverride,
}: Props) {
    return (
        <>
            <Head title="Base de règles" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Base de règles"
                    description="Chaque règle est une ligne de la base, jamais une ligne de code. L'origine de son seuil est toujours affichée."
                />

                {/* Les quatre nombres se calculent, ils ne s'écrivent pas
                    en dur (SPEC-CORRECTION section 6). */}
                <p
                    className="rounded-xl border bg-card p-4 font-medium tabular-nums md:p-5"
                    data-test="rules-summary"
                >
                    {summary.rules} règle{summary.rules > 1 ? 's' : ''},{' '}
                    {summary.sources} source{summary.sources > 1 ? 's' : ''},{' '}
                    {summary.foreign_thresholds} seuil
                    {summary.foreign_thresholds > 1 ? 's' : ''} étranger
                    {summary.foreign_thresholds > 1 ? 's' : ''},{' '}
                    {summary.convention_thresholds} seuil
                    {summary.convention_thresholds > 1 ? 's' : ''} fixé
                    {summary.convention_thresholds > 1 ? 's' : ''} par
                    convention
                </p>

                <div
                    className="flex items-start gap-3 rounded-xl border border-dashed p-4"
                    data-test="interrogable-fields"
                >
                    <Info className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                    <div className="space-y-1">
                        <p className="text-sm font-medium">
                            Les {fields.length} champs interrogeables
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {fields.map((field) => field.label).join(', ')}.
                        </p>
                    </div>
                </div>

                {rules.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title="Base de règles vide"
                        description="Chargez le fichier des règles agronomiques pour alimenter le moteur."
                    />
                ) : (
                    <ul className="space-y-4">
                        {rules.map((rule) => (
                            <li
                                key={rule.id}
                                className={cn(
                                    'space-y-4 rounded-xl border bg-card p-4 md:p-6',
                                    rule.is_overridden && 'opacity-60',
                                )}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-heading font-semibold tabular-nums">
                                                {rule.code}
                                            </span>
                                            {rule.is_override && (
                                                <Badge variant="default">
                                                    Surcharge de votre
                                                    exploitation
                                                </Badge>
                                            )}
                                            {rule.is_overridden && (
                                                <Badge variant="outline">
                                                    Remplacée par votre
                                                    surcharge
                                                </Badge>
                                            )}
                                            {!rule.enabled && (
                                                <Badge variant="outline">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {rule.crop ?? 'Toute culture'} ·{' '}
                                            {rule.family_label}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            className={cn(
                                                'border-transparent',
                                                SEVERITY_TONES[rule.severity],
                                            )}
                                        >
                                            {rule.severity_label}
                                        </Badge>
                                        <Badge
                                            className={cn(
                                                'border-transparent',
                                                THRESHOLD_TONES[
                                                    rule.threshold_status
                                                ],
                                            )}
                                        >
                                            {rule.threshold_status_label}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <p className="font-medium">
                                        {rule.message}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {rule.action}
                                    </p>
                                </div>

                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Source
                                        </dt>
                                        <dd>{rule.source}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Gain estimé
                                        </dt>
                                        <dd className="tabular-nums">
                                            {rule.estimated_gain ??
                                                'Non chiffré'}
                                        </dd>
                                    </div>
                                </dl>

                                {/* Ce que la règle vérifie, en français
                                    lisible et sans clic supplémentaire :
                                    jamais le JSON brut (section 6). */}
                                <div
                                    className="rounded-lg border p-3 text-sm"
                                    data-test="condition-sentence"
                                >
                                    <p>
                                        <span className="text-muted-foreground">
                                            La règle vérifie :{' '}
                                        </span>
                                        {rule.condition_sentence}
                                    </p>
                                    {rule.is_override &&
                                        rule.common_condition_sentence && (
                                            <p className="mt-2 text-muted-foreground">
                                                Seuil de la fiche nationale :{' '}
                                                {rule.common_condition_sentence}
                                            </p>
                                        )}
                                </div>

                                {canOverride && !rule.is_overridden && (
                                    <OverrideDialog
                                        rule={rule}
                                        severities={severities}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

RulesIndex.layout = {
    breadcrumbs: [{ title: 'Base de règles', href: index() }],
};
