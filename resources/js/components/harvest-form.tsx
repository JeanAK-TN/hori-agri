import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { store } from '@/routes/harvests';

export type Option = { value: string; label: string };

type Props = {
    cycleId: number;
    qualities: Option[];
};

const EMPTY = {
    date: '',
    quantity_kg: '',
    unit_price: '',
    quality: '',
    post_harvest_losses_kg: '',
};

/**
 * Saisie d'une récolte. Plusieurs récoltes sont possibles pour un même
 * cycle (SPEC section 6.2) : le formulaire reste disponible tant que le
 * cycle accepte la saisie.
 */
export default function HarvestForm({ cycleId, qualities }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm(EMPTY);

    const [resetKey, setResetKey] = useState(0);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        post(store(cycleId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setResetKey((key) => key + 1);
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-6" data-test="harvest-form">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="harvest_date">Date</Label>
                    <Input
                        id="harvest_date"
                        type="date"
                        required
                        className="h-12"
                        value={data.date}
                        onChange={(event) =>
                            setData('date', event.target.value)
                        }
                    />
                    <InputError message={errors.date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="quantity_kg">Quantité récoltée</Label>
                    <div className="flex items-stretch gap-2">
                        <Input
                            id="quantity_kg"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0"
                            required
                            className="h-12"
                            placeholder="675"
                            value={data.quantity_kg}
                            onChange={(event) =>
                                setData('quantity_kg', event.target.value)
                            }
                        />
                        <span className="flex h-12 items-center rounded-md border bg-muted px-4 text-sm text-muted-foreground">
                            kg
                        </span>
                    </div>
                    <InputError message={errors.quantity_kg} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="unit_price">
                        Prix de vente au kilogramme
                    </Label>
                    <div className="flex items-stretch gap-2">
                        <Input
                            id="unit_price"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0"
                            className="h-12"
                            placeholder="200"
                            value={data.unit_price}
                            onChange={(event) =>
                                setData('unit_price', event.target.value)
                            }
                        />
                        <span className="flex h-12 items-center rounded-md border bg-muted px-4 text-sm text-muted-foreground">
                            FCFA
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Sans lui, la marge du cycle reste incalculable.
                    </p>
                    <InputError message={errors.unit_price} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="quality">Qualité (facultative)</Label>
                    <Select
                        key={`quality-${resetKey}`}
                        value={data.quality}
                        onValueChange={(value) => setData('quality', value)}
                    >
                        <SelectTrigger id="quality" className="w-full">
                            <SelectValue placeholder="Non renseignée" />
                        </SelectTrigger>
                        <SelectContent>
                            {qualities.map((quality) => (
                                <SelectItem
                                    key={quality.value}
                                    value={quality.value}
                                >
                                    {quality.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.quality} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="post_harvest_losses_kg">
                    Pertes après récolte (facultatives)
                </Label>
                <div className="flex items-stretch gap-2">
                    <Input
                        id="post_harvest_losses_kg"
                        type="number"
                        inputMode="decimal"
                        step="0.01"
                        min="0"
                        className="h-12"
                        value={data.post_harvest_losses_kg}
                        onChange={(event) =>
                            setData(
                                'post_harvest_losses_kg',
                                event.target.value,
                            )
                        }
                    />
                    <span className="flex h-12 items-center rounded-md border bg-muted px-4 text-sm text-muted-foreground">
                        kg
                    </span>
                </div>
                <InputError message={errors.post_harvest_losses_kg} />
            </div>

            <Button
                type="submit"
                className="h-12"
                disabled={processing}
                data-test="record-harvest"
            >
                {processing && <Spinner />}
                Enregistrer la récolte
            </Button>
        </form>
    );
}
