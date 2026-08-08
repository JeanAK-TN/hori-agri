import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import CropCycleController from '@/actions/App/Http/Controllers/CropCycleController';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
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
import { index } from '@/routes/cycles';

type Option = { id: number; label: string };
type VarietyOption = Option & { crop_id: number };

type Props = {
    plots: Option[];
    campaigns: Option[];
    crops: Option[];
    varieties: VarietyOption[];
    selectedPlotId: number | null;
};

const NO_VARIETY = 'aucune';

export default function CycleCreate({
    plots,
    campaigns,
    crops,
    varieties,
    selectedPlotId,
}: Props) {
    // La variété n'a de sens que sous sa culture : la liste se restreint
    // dès qu'une culture est choisie.
    const [cropId, setCropId] = useState<string | undefined>(undefined);

    const availableVarieties = varieties.filter(
        (variety) => String(variety.crop_id) === cropId,
    );

    return (
        <>
            <Head title="Ouvrir un cycle cultural" />

            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ouvrir un cycle cultural"
                    description="Une parcelle, une culture et une campagne : ce trio ne peut être ouvert qu'une seule fois."
                >
                    <Button asChild variant="outline" className="h-12">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Retour à la liste
                        </Link>
                    </Button>
                </PageHeader>

                <Form
                    {...CropCycleController.store.form()}
                    transform={(data) => ({
                        ...data,
                        variety_id:
                            data.variety_id === NO_VARIETY
                                ? null
                                : data.variety_id,
                    })}
                    className="max-w-2xl space-y-6 rounded-xl border bg-card p-4 md:p-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="plot_id">Parcelle</Label>
                                <Select
                                    name="plot_id"
                                    defaultValue={
                                        selectedPlotId
                                            ? String(selectedPlotId)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger
                                        id="plot_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choisir une parcelle" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plots.map((plot) => (
                                            <SelectItem
                                                key={plot.id}
                                                value={String(plot.id)}
                                            >
                                                {plot.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.plot_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="campaign_id">Campagne</Label>
                                <Select name="campaign_id">
                                    <SelectTrigger
                                        id="campaign_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choisir une campagne" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <InputError message={errors.campaign_id} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="crop_id">Culture</Label>
                                    <Select
                                        name="crop_id"
                                        value={cropId}
                                        onValueChange={setCropId}
                                    >
                                        <SelectTrigger
                                            id="crop_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choisir une culture" />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <InputError message={errors.crop_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="variety_id">
                                        Variété (facultative)
                                    </Label>
                                    <Select
                                        name="variety_id"
                                        defaultValue={NO_VARIETY}
                                        disabled={cropId === undefined}
                                    >
                                        <SelectTrigger
                                            id="variety_id"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NO_VARIETY}>
                                                Variété inconnue
                                            </SelectItem>
                                            {availableVarieties.map(
                                                (variety) => (
                                                    <SelectItem
                                                        key={variety.id}
                                                        value={String(
                                                            variety.id,
                                                        )}
                                                    >
                                                        {variety.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.variety_id} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="sowing_date">
                                        Date de semis
                                    </Label>
                                    <Input
                                        id="sowing_date"
                                        name="sowing_date"
                                        type="date"
                                        required
                                        className="h-12"
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Elle doit tomber dans les bornes de la
                                        campagne.
                                    </p>
                                    <InputError message={errors.sowing_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="density">
                                        Densité (facultative)
                                    </Label>
                                    <div className="flex items-stretch gap-2">
                                        <Input
                                            id="density"
                                            name="density"
                                            type="number"
                                            inputMode="numeric"
                                            min="1"
                                            className="h-12"
                                            placeholder="50000"
                                        />
                                        <span className="flex h-12 items-center rounded-md border bg-muted px-3 text-sm whitespace-nowrap text-muted-foreground">
                                            plants/ha
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        À laisser vide si le producteur ne la
                                        connaît pas.
                                    </p>
                                    <InputError message={errors.density} />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    className="h-12"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Ouvrir le cycle
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-12"
                                >
                                    <Link href={index()}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CycleCreate.layout = {
    breadcrumbs: [
        { title: 'Cycles culturaux', href: index() },
        { title: 'Ouvrir un cycle', href: index() },
    ],
};
