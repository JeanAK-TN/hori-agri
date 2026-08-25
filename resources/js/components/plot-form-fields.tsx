import type { FormDataConvertible } from '@inertiajs/core';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PlotFormValues = {
    farmer_id: number;
    name: string;
    area_ha: string;
    soil_type: string;
    soil_preparation: string | null;
    latitude: string | null;
    longitude: string | null;
    previous_crop: string | null;
};

export const NO_SOIL_PREPARATION = 'non_renseignee';

/**
 * La préparation du sol est facultative : la valeur sentinelle du
 * sélecteur redevient null avant l'envoi.
 */
export function normalisePlotData(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        soil_preparation:
            data.soil_preparation === NO_SOIL_PREPARATION
                ? null
                : data.soil_preparation,
    };
}

export type Option = { value: string; label: string };

export type FarmerOption = { id: number; label: string };

type Props = {
    errors: Record<string, string>;
    soilTypes: Option[];
    soilPreparations: Option[];
    farmers: FarmerOption[];
    plot?: PlotFormValues;
    defaultFarmerId?: number | null;
};

export default function PlotFormFields({
    errors,
    soilTypes,
    soilPreparations,
    farmers,
    plot,
    defaultFarmerId,
}: Props) {
    const selectedFarmer = plot?.farmer_id ?? defaultFarmerId ?? undefined;

    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="farmer_id">Producteur</Label>
                <Select
                    name="farmer_id"
                    defaultValue={
                        selectedFarmer ? String(selectedFarmer) : undefined
                    }
                >
                    <SelectTrigger id="farmer_id" className="w-full">
                        <SelectValue placeholder="Choisir un producteur" />
                    </SelectTrigger>
                    <SelectContent>
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
                <InputError message={errors.farmer_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nom de la parcelle</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    className="h-12"
                    placeholder="Le nom d'usage donné par le producteur"
                    defaultValue={plot?.name}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="area_ha">Surface</Label>
                    <div className="flex items-stretch gap-2">
                        <Input
                            id="area_ha"
                            name="area_ha"
                            type="number"
                            inputMode="decimal"
                            step="0.001"
                            min="0.001"
                            required
                            className="h-12"
                            placeholder="0,750"
                            defaultValue={plot?.area_ha}
                        />
                        <span className="flex h-12 items-center rounded-md border bg-muted px-4 text-sm text-muted-foreground">
                            ha
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Sans elle, aucun indicateur à l'hectare n'est
                        calculable.
                    </p>
                    <InputError message={errors.area_ha} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="soil_type">Type de sol</Label>
                    <Select name="soil_type" defaultValue={plot?.soil_type}>
                        <SelectTrigger id="soil_type" className="w-full">
                            <SelectValue placeholder="Choisir un type de sol" />
                        </SelectTrigger>
                        <SelectContent>
                            {soilTypes.map((soil) => (
                                <SelectItem key={soil.value} value={soil.value}>
                                    {soil.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.soil_type} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="soil_preparation">
                    Préparation du sol (facultative)
                </Label>
                <Select
                    name="soil_preparation"
                    defaultValue={plot?.soil_preparation ?? NO_SOIL_PREPARATION}
                >
                    <SelectTrigger id="soil_preparation" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_SOIL_PREPARATION}>
                            Non renseignée
                        </SelectItem>
                        {soilPreparations.map((preparation) => (
                            <SelectItem
                                key={preparation.value}
                                value={preparation.value}
                            >
                                {preparation.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                    Planches surélevées, billons ou culture à plat.
                </p>
                <InputError message={errors.soil_preparation} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="previous_crop">
                    Culture précédente (facultative)
                </Label>
                <Input
                    id="previous_crop"
                    name="previous_crop"
                    className="h-12"
                    placeholder="Ce qui était cultivé la campagne d'avant"
                    defaultValue={plot?.previous_crop ?? ''}
                />
                <p className="text-sm text-muted-foreground">
                    Elle sert aux règles de rotation.
                </p>
                <InputError message={errors.previous_crop} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="latitude">Latitude (facultative)</Label>
                    <Input
                        id="latitude"
                        name="latitude"
                        type="number"
                        inputMode="decimal"
                        step="0.0000001"
                        className="h-12"
                        placeholder="9.5511"
                        defaultValue={plot?.latitude ?? ''}
                    />
                    <InputError message={errors.latitude} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="longitude">Longitude (facultative)</Label>
                    <Input
                        id="longitude"
                        name="longitude"
                        type="number"
                        inputMode="decimal"
                        step="0.0000001"
                        className="h-12"
                        placeholder="1.1861"
                        defaultValue={plot?.longitude ?? ''}
                    />
                    <InputError message={errors.longitude} />
                </div>
            </div>
        </div>
    );
}
