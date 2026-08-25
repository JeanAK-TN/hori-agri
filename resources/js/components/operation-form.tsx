import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
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
import { store } from '@/routes/operations';

export type OperationTypeOption = {
    value: string;
    label: string;
    requires_input: boolean;
};

export type Option = { value: string; label: string };

type Props = {
    cycleId: number;
    operationTypes: OperationTypeOption[];
    inputTypes: Option[];
    units: Option[];
};

const EMPTY = {
    type: '',
    input_type: '',
    date: '',
    product: '',
    quantity: '',
    unit: '',
    cost: '',
    observed_rate: '',
    notes: '',
};

/**
 * Saisie d'une opération. C'est l'écran le plus important du projet : il
 * porte l'hypothèse selon laquelle la saisie est possible sur un
 * téléphone d'entrée de gamme (SPEC section 11.1).
 *
 * Le formulaire ne demande jamais une dose à l'hectare. Il demande une
 * quantité et une unité telles que le technicien les compte, le sac de
 * 50 kg étant l'unité de terrain. La dose est calculée ensuite.
 *
 * useForm plutôt que <Form> : les champs d'intrant n'apparaissent que
 * pour les types qui apportent un produit, et la saisie déjà faite doit
 * survivre à un refus du serveur.
 */
export default function OperationForm({
    cycleId,
    operationTypes,
    inputTypes,
    units,
}: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm(EMPTY);

    const [resetKey, setResetKey] = useState(0);

    const selectedType = useMemo(
        () => operationTypes.find((type) => type.value === data.type),
        [operationTypes, data.type],
    );

    const bringsInput = selectedType?.requires_input === true;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        post(store(cycleId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                // Force les listes déroulantes non contrôlées à revenir
                // à leur état vide après un enregistrement réussi.
                setResetKey((key) => key + 1);
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-6"
            data-test="operation-form"
        >
            <div className="grid gap-2">
                <Label htmlFor="type">Type d'opération</Label>
                <Select
                    key={`type-${resetKey}`}
                    value={data.type}
                    onValueChange={(value) => {
                        setData((current) => ({
                            ...current,
                            type: value,
                            // Un type qui n'apporte rien ne porte pas
                            // d'intrant : le champ est vidé.
                            input_type:
                                operationTypes.find((t) => t.value === value)
                                    ?.requires_input === true
                                    ? current.input_type
                                    : '',
                        }));
                    }}
                >
                    <SelectTrigger id="type" className="w-full">
                        <SelectValue placeholder="Choisir un type" />
                    </SelectTrigger>
                    <SelectContent>
                        {operationTypes.map((type) => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="date">Date</Label>
                <Input
                    id="date"
                    name="date"
                    type="date"
                    required
                    className="h-12"
                    value={data.date}
                    onChange={(event) => setData('date', event.target.value)}
                />
                <InputError message={errors.date} />
            </div>

            {/* Nature de l'intrant, quantité, unité et coût n'apparaissent
                que pour les types qui apportent un produit (section 11.1). */}
            {bringsInput && (
                <div
                    className="grid gap-6 rounded-lg border bg-accent/40 p-4"
                    data-test="input-fields"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="input_type">Nature de l'intrant</Label>
                        <Select
                            key={`input-${resetKey}`}
                            value={data.input_type}
                            onValueChange={(value) =>
                                setData('input_type', value)
                            }
                        >
                            <SelectTrigger id="input_type" className="w-full">
                                <SelectValue placeholder="Choisir un intrant" />
                            </SelectTrigger>
                            <SelectContent>
                                {inputTypes.map((input) => (
                                    <SelectItem
                                        key={input.value}
                                        value={input.value}
                                    >
                                        {input.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.input_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product">
                            Produit (nom commercial, facultatif)
                        </Label>
                        <Input
                            id="product"
                            name="product"
                            className="h-12"
                            placeholder="NPK 15-15-15"
                            value={data.product}
                            onChange={(event) =>
                                setData('product', event.target.value)
                            }
                        />
                        <InputError message={errors.product} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="quantity">Quantité</Label>
                            <Input
                                id="quantity"
                                name="quantity"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0"
                                className="h-12"
                                placeholder="2"
                                value={data.quantity}
                                onChange={(event) =>
                                    setData('quantity', event.target.value)
                                }
                            />
                            <InputError message={errors.quantity} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="unit">Unité</Label>
                            <Select
                                key={`unit-${resetKey}`}
                                value={data.unit}
                                onValueChange={(value) =>
                                    setData('unit', value)
                                }
                            >
                                <SelectTrigger id="unit" className="w-full">
                                    <SelectValue placeholder="Choisir une unité" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem
                                            key={unit.value}
                                            value={unit.value}
                                        >
                                            {unit.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.unit} />
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground">
                        Comptez comme sur le terrain : 2 sacs de 50 kg pour la
                        parcelle entière, pas une dose à l'hectare.
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="cost">Coût (facultatif)</Label>
                        <div className="flex items-stretch gap-2">
                            <Input
                                id="cost"
                                name="cost"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0"
                                className="h-12"
                                placeholder="24000"
                                value={data.cost}
                                onChange={(event) =>
                                    setData('cost', event.target.value)
                                }
                            />
                            <span className="flex h-12 items-center rounded-md border bg-muted px-4 text-sm text-muted-foreground">
                                FCFA
                            </span>
                        </div>
                        <InputError message={errors.cost} />
                    </div>
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="observed_rate">Taux observé (facultatif)</Label>
                <Input
                    id="observed_rate"
                    name="observed_rate"
                    type="number"
                    inputMode="decimal"
                    step="0.01"
                    min="0"
                    className="h-12"
                    value={data.observed_rate}
                    onChange={(event) =>
                        setData('observed_rate', event.target.value)
                    }
                />
                <p className="text-sm text-muted-foreground">
                    Le taux relevé lors d'une observation sanitaire, par exemple
                    un taux d'attaque.
                </p>
                <InputError message={errors.observed_rate} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">Remarques (facultatives)</Label>
                <Input
                    id="notes"
                    name="notes"
                    className="h-12"
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
                <InputError message={errors.notes} />
            </div>

            <Button
                type="submit"
                className="h-12"
                disabled={processing}
                data-test="record-operation"
            >
                {processing && <Spinner />}
                Enregistrer l'opération
            </Button>
        </form>
    );
}
