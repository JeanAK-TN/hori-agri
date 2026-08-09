import { Form, Head, router } from '@inertiajs/react';
import { CheckCircle2, Lightbulb, X } from 'lucide-react';
import { useState } from 'react';
import FeedbackController from '@/actions/App/Http/Controllers/FeedbackController';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PaginationNav from '@/components/pagination-nav';
import RecoStatusBadge from '@/components/reco-status-badge';
import type { RecoStatusValue } from '@/components/reco-status-badge';
import SeverityBadge from '@/components/severity-badge';
import type { SeverityValue } from '@/components/severity-badge';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/recommendations';
import type { Paginated } from '@/types';

type RecommendationRow = {
    id: number;
    finding: string;
    action: string;
    source: string;
    estimated_gain: string | null;
    rule_code: string;
    severity: SeverityValue;
    severity_label: string;
    threshold_status: string;
    threshold_status_label: string;
    status: RecoStatusValue;
    status_label: string;
    generated_at_label: string;
    cycle: {
        id: number;
        crop: string;
        campaign: string;
        plot: string;
        farmer: string;
        village: string;
    };
    feedback: {
        applied: boolean;
        comment: string | null;
        observed_result: string | null;
    } | null;
};

type Props = {
    recommendations: Paginated<RecommendationRow>;
    filters: { statut: string | null; producteur: number | null };
    statuses: { value: string; label: string }[];
    farmers: { id: number; label: string }[];
    canRespond: boolean;
};

const ALL = 'toutes';

/**
 * Le retour de terrain : ce qui a été fait, et ce qui a été observé.
 */
function AppliedDialog({
    recommendation,
}: {
    recommendation: RecommendationRow;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    className="h-12 flex-1 sm:flex-none"
                    data-test={`apply-${recommendation.id}`}
                >
                    <CheckCircle2 className="size-4" />
                    J'ai appliqué
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Ce que vous avez fait</DialogTitle>
                    <DialogDescription>
                        Votre retour reste attaché à ce conseil. Il sert à
                        savoir ce qui marche sur vos parcelles.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...FeedbackController.store.form(recommendation.id)}
                    transform={(data) => ({ ...data, applied: true })}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`comment-${recommendation.id}`}>
                                    Comment vous l'avez fait
                                </Label>
                                <Textarea
                                    id={`comment-${recommendation.id}`}
                                    name="comment"
                                    rows={3}
                                    placeholder="Par exemple : deux sacs d'urée épandus le 12 juin."
                                />
                                <InputError message={errors.comment} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`result-${recommendation.id}`}>
                                    Ce que vous avez observé
                                </Label>
                                <Textarea
                                    id={`result-${recommendation.id}`}
                                    name="observed_result"
                                    rows={3}
                                    placeholder="Par exemple : le maïs a mieux tenu la sécheresse."
                                />
                                <InputError message={errors.observed_result} />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                    data-test="save-feedback"
                                >
                                    {processing && <Spinner />}
                                    Enregistrer mon retour
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function RecommendationsIndex({
    recommendations,
    filters,
    statuses,
    farmers,
    canRespond,
}: Props) {
    const applyFilters = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };

        router.get(
            index().url,
            {
                statut: merged.statut ?? undefined,
                producteur: merged.producteur ?? undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const dismiss = (id: number) => {
        router.patch(
            update(id).url,
            { status: 'dismissed' },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Recommandations" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Recommandations"
                    description="Le constat, ce qu'il y a à faire, et d'où vient le seuil. Rien n'est caché derrière un repli."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="statut">Statut</Label>
                        <Select
                            value={filters.statut ?? ALL}
                            onValueChange={(value) =>
                                applyFilters({
                                    statut: value === ALL ? null : value,
                                })
                            }
                        >
                            <SelectTrigger id="statut" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Tous</SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {farmers.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="producteur">Producteur</Label>
                            <Select
                                value={
                                    filters.producteur === null
                                        ? ALL
                                        : String(filters.producteur)
                                }
                                onValueChange={(value) =>
                                    applyFilters({
                                        producteur:
                                            value === ALL
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="producteur"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Tous</SelectItem>
                                    {farmers.map((farmer) => (
                                        <SelectItem
                                            key={farmer.id}
                                            value={String(farmer.id)}
                                        >
                                            {farmer.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {recommendations.data.length === 0 ? (
                    <EmptyState
                        icon={Lightbulb}
                        title="Aucune recommandation"
                        description="Les conseils apparaissent d'eux-mêmes dès que les opérations et les récoltes d'un cycle sont saisies."
                    />
                ) : (
                    <ul className="space-y-4">
                        {recommendations.data.map((recommendation) => (
                            <li
                                key={recommendation.id}
                                className="space-y-5 rounded-xl border bg-card p-4 md:p-6"
                                data-test={`recommendation-${recommendation.id}`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <p className="font-heading font-semibold">
                                            {recommendation.cycle.crop} ·{' '}
                                            {recommendation.cycle.plot}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {recommendation.cycle.farmer} ·{' '}
                                            {recommendation.cycle.village} ·{' '}
                                            {recommendation.cycle.campaign}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <SeverityBadge
                                            severity={recommendation.severity}
                                            label={
                                                recommendation.severity_label
                                            }
                                        />
                                        <RecoStatusBadge
                                            status={recommendation.status}
                                            label={recommendation.status_label}
                                        />
                                    </div>
                                </div>

                                {/*
                                    Les quatre blocs dans l'ordre imposé :
                                    constat, action, source, gain estimé
                                    (SPEC section 11.2).
                                */}
                                <div className="space-y-4">
                                    <section data-test="finding">
                                        <h3 className="text-sm font-medium text-muted-foreground">
                                            Constat
                                        </h3>
                                        <p className="mt-1">
                                            {recommendation.finding}
                                        </p>
                                    </section>

                                    <section data-test="action">
                                        <h3 className="text-sm font-medium text-muted-foreground">
                                            À faire
                                        </h3>
                                        <p className="mt-1">
                                            {recommendation.action}
                                        </p>
                                    </section>

                                    <section data-test="source">
                                        <h3 className="text-sm font-medium text-muted-foreground">
                                            Source
                                        </h3>
                                        <p className="mt-1 text-sm">
                                            {recommendation.source}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            className="mt-2"
                                        >
                                            {
                                                recommendation.threshold_status_label
                                            }
                                        </Badge>
                                    </section>

                                    <section data-test="estimated-gain">
                                        <h3 className="text-sm font-medium text-muted-foreground">
                                            Gain estimé
                                        </h3>
                                        <p className="mt-1 tabular-nums">
                                            {recommendation.estimated_gain ??
                                                'Non chiffré'}
                                        </p>
                                    </section>
                                </div>

                                {/* De quelle règle ce conseil est sorti, et quand. */}
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    Règle {recommendation.rule_code} · calculée
                                    le {recommendation.generated_at_label}
                                </p>

                                {recommendation.feedback !== null && (
                                    <div
                                        className="rounded-lg border border-dashed p-4"
                                        data-test="feedback"
                                    >
                                        <p className="text-sm font-medium">
                                            {recommendation.feedback.applied
                                                ? 'Vous avez appliqué ce conseil.'
                                                : 'Vous avez écarté ce conseil.'}
                                        </p>
                                        {recommendation.feedback.comment && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    recommendation.feedback
                                                        .comment
                                                }
                                            </p>
                                        )}
                                        {recommendation.feedback
                                            .observed_result && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    recommendation.feedback
                                                        .observed_result
                                                }
                                            </p>
                                        )}
                                    </div>
                                )}

                                {canRespond && (
                                    <div className="flex flex-wrap gap-3">
                                        <AppliedDialog
                                            recommendation={recommendation}
                                        />
                                        <Button
                                            variant="outline"
                                            className="h-12 flex-1 sm:flex-none"
                                            onClick={() =>
                                                dismiss(recommendation.id)
                                            }
                                            data-test={`dismiss-${recommendation.id}`}
                                        >
                                            <X className="size-4" />
                                            Écarter
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <PaginationNav
                    paginator={recommendations}
                    label="recommandations"
                />
            </div>
        </>
    );
}

RecommendationsIndex.layout = {
    breadcrumbs: [{ title: 'Recommandations', href: index() }],
};
