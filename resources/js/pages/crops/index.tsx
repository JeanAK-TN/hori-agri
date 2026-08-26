import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Wheat } from 'lucide-react';
import { useState } from 'react';
import CropController from '@/actions/App/Http/Controllers/CropController';
import VarietyController from '@/actions/App/Http/Controllers/VarietyController';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
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
import { index } from '@/routes/crops';

type Variety = {
    id: number;
    crop_id: number;
    name: string;
    cycle_days: number | null;
    potential_yield_kg_ha: string | null;
};

type Crop = {
    id: number;
    name: string;
    family: string | null;
    cycle_days: number;
    reference_density: number;
    reference_yield_kg_ha: string;
    npk_needs: { n?: number; p?: number; k?: number } | null;
    cycles_count: number;
    varieties: Variety[];
};

type Props = {
    crops: Crop[];
};

function CropFields({
    errors,
    crop,
}: {
    errors: Record<string, string>;
    crop?: Crop;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nom</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autoFocus
                        className="h-12"
                        defaultValue={crop?.name}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="family">Famille botanique</Label>
                    <Input
                        id="family"
                        name="family"
                        className="h-12"
                        placeholder="Poaceae"
                        defaultValue={crop?.family ?? ''}
                    />
                    <InputError message={errors.family} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="cycle_days">Durée du cycle (jours)</Label>
                    <Input
                        id="cycle_days"
                        name="cycle_days"
                        type="number"
                        min="1"
                        required
                        className="h-12"
                        defaultValue={crop?.cycle_days}
                    />
                    <InputError message={errors.cycle_days} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="reference_density">
                        Densité de référence
                    </Label>
                    <Input
                        id="reference_density"
                        name="reference_density"
                        type="number"
                        min="1"
                        required
                        className="h-12"
                        defaultValue={crop?.reference_density}
                    />
                    <InputError message={errors.reference_density} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="reference_yield_kg_ha">
                        Rendement de référence
                    </Label>
                    <Input
                        id="reference_yield_kg_ha"
                        name="reference_yield_kg_ha"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        className="h-12"
                        defaultValue={crop?.reference_yield_kg_ha}
                    />
                    <InputError message={errors.reference_yield_kg_ha} />
                </div>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    Besoins NPK (kg/ha)
                </legend>
                <div className="grid gap-4 sm:grid-cols-3">
                    {(['n', 'p', 'k'] as const).map((element) => (
                        <div key={element} className="grid gap-2">
                            <Label htmlFor={`npk_${element}`}>
                                {element.toUpperCase()}
                            </Label>
                            <Input
                                id={`npk_${element}`}
                                name={`npk_needs[${element}]`}
                                type="number"
                                step="0.01"
                                min="0"
                                className="h-12"
                                defaultValue={crop?.npk_needs?.[element] ?? ''}
                            />
                        </div>
                    ))}
                </div>
                <InputError message={errors.npk_needs} />
            </fieldset>
        </div>
    );
}

function CropDialog({ crop }: { crop?: Crop }) {
    const [open, setOpen] = useState(false);
    const isEdit = crop !== undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {isEdit ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-12"
                        aria-label={`Modifier ${crop.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                ) : (
                    <Button className="h-12">
                        <Plus className="size-4" />
                        Nouvelle culture
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Modifier la culture' : 'Nouvelle culture'}
                    </DialogTitle>
                    <DialogDescription>
                        Ces valeurs de référence servent au moteur de
                        recommandations : elles doivent venir d'une source
                        agronomique publiée.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(isEdit
                        ? CropController.update.form(crop.id)
                        : CropController.store.form())}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CropFields errors={errors} crop={crop} />
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function VarietyDialog({
    cropId,
    cropName,
    variety,
}: {
    cropId: number;
    cropName: string;
    variety?: Variety;
}) {
    const [open, setOpen] = useState(false);
    const isEdit = variety !== undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {isEdit ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-12"
                        aria-label={`Modifier ${variety.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                ) : (
                    <Button variant="outline" className="h-12">
                        <Plus className="size-4" />
                        Ajouter une variété
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Modifier la variété' : 'Nouvelle variété'}
                    </DialogTitle>
                    <DialogDescription>{cropName}</DialogDescription>
                </DialogHeader>

                <Form
                    {...(isEdit
                        ? VarietyController.update.form(variety.id)
                        : VarietyController.store.form())}
                    transform={(data) => ({ ...data, crop_id: cropId })}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="variety_name">Nom</Label>
                                    <Input
                                        id="variety_name"
                                        name="name"
                                        required
                                        autoFocus
                                        className="h-12"
                                        defaultValue={variety?.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="variety_cycle_days">
                                            Durée du cycle (jours)
                                        </Label>
                                        <Input
                                            id="variety_cycle_days"
                                            name="cycle_days"
                                            type="number"
                                            min="1"
                                            className="h-12"
                                            defaultValue={
                                                variety?.cycle_days ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.cycle_days}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="variety_potential">
                                            Rendement potentiel (kg/ha)
                                        </Label>
                                        <Input
                                            id="variety_potential"
                                            name="potential_yield_kg_ha"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="h-12"
                                            defaultValue={
                                                variety?.potential_yield_kg_ha ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.potential_yield_kg_ha
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function CropsIndex({ crops }: Props) {
    return (
        <>
            <Head title="Référentiel des cultures" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Référentiel des cultures"
                    description="Commun à toutes les exploitations. Les valeurs de référence alimentent le moteur de recommandations."
                >
                    <CropDialog />
                </PageHeader>

                {crops.length === 0 ? (
                    <EmptyState
                        icon={Wheat}
                        title="Référentiel vide"
                        description="Chargez les cultures depuis les fichiers de référence, ou ajoutez-en une à la main."
                    />
                ) : (
                    <div className="space-y-4">
                        {crops.map((crop) => (
                            <section
                                key={crop.id}
                                className="space-y-4 rounded-xl border bg-card p-4 md:p-6"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <h2 className="font-heading text-lg font-semibold">
                                            {crop.name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {crop.family ?? 'Famille inconnue'}{' '}
                                            · {crop.cycles_count} cycle
                                            {crop.cycles_count > 1
                                                ? 's'
                                                : ''}{' '}
                                            enregistré
                                            {crop.cycles_count > 1 ? 's' : ''}
                                        </p>
                                    </div>
                                    <CropDialog crop={crop} />
                                </div>

                                <dl className="grid gap-4 sm:grid-cols-3">
                                    <div className="space-y-1">
                                        <dt className="text-sm text-muted-foreground">
                                            Durée du cycle
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {crop.cycle_days} jours
                                        </dd>
                                    </div>
                                    <div className="space-y-1">
                                        <dt className="text-sm text-muted-foreground">
                                            Densité de référence
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {crop.reference_density} plants/ha
                                        </dd>
                                    </div>
                                    <div className="space-y-1">
                                        <dt className="text-sm text-muted-foreground">
                                            Rendement de référence
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {crop.reference_yield_kg_ha} kg/ha
                                        </dd>
                                    </div>
                                </dl>

                                {crop.npk_needs && (
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        Besoins NPK : N{' '}
                                        {crop.npk_needs.n ?? 'non chiffré'} · P{' '}
                                        {crop.npk_needs.p ?? 'non chiffré'} · K{' '}
                                        {crop.npk_needs.k ?? 'non chiffré'}{' '}
                                        kg/ha
                                    </p>
                                )}

                                <div className="space-y-3 border-t pt-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <h3 className="font-medium">
                                            Variétés
                                        </h3>
                                        <VarietyDialog
                                            cropId={crop.id}
                                            cropName={crop.name}
                                        />
                                    </div>

                                    {crop.varieties.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Aucune variété enregistrée pour
                                            cette culture.
                                        </p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {crop.varieties.map((variety) => (
                                                <li
                                                    key={variety.id}
                                                    className="flex min-h-14 flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                                >
                                                    <div className="space-y-1">
                                                        <p className="font-medium">
                                                            {variety.name}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground tabular-nums">
                                                            {variety.cycle_days ??
                                                                '?'}{' '}
                                                            jours ·{' '}
                                                            {variety.potential_yield_kg_ha ??
                                                                '?'}{' '}
                                                            kg/ha
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="secondary">
                                                            Variété
                                                        </Badge>
                                                        <VarietyDialog
                                                            cropId={crop.id}
                                                            cropName={crop.name}
                                                            variety={variety}
                                                        />
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CropsIndex.layout = {
    breadcrumbs: [{ title: 'Référentiel des cultures', href: index() }],
};
