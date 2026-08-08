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

export type FarmerFormValues = {
    first_name: string;
    last_name: string;
    gender: string | null;
    birth_year: number | null;
    experience_years: number | null;
    village: string;
    phone: string | null;
    user_id: number | null;
};

export type Option = { value: string; label: string };

export type LinkableAccount = { id: number; name: string; phone: string };

type Props = {
    errors: Record<string, string>;
    genders: Option[];
    availableAccounts: LinkableAccount[];
    farmer?: FarmerFormValues;
};

const NO_ACCOUNT = 'aucun';

/**
 * Fields of a producer record, shared by the creation and the correction
 * screens. Kept short and one column deep on a phone (SPEC rule 5).
 */
export default function FarmerFormFields({
    errors,
    genders,
    availableAccounts,
    farmer,
}: Props) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="first_name">Prénom</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        required
                        autoFocus
                        className="h-12"
                        defaultValue={farmer?.first_name}
                    />
                    <InputError message={errors.first_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="last_name">Nom</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        required
                        className="h-12"
                        defaultValue={farmer?.last_name}
                    />
                    <InputError message={errors.last_name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="village">Village</Label>
                <Input
                    id="village"
                    name="village"
                    required
                    className="h-12"
                    defaultValue={farmer?.village}
                />
                <p className="text-sm text-muted-foreground">
                    Il sert de zone de comparaison entre parcelles semblables.
                </p>
                <InputError message={errors.village} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Téléphone (facultatif)</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    inputMode="tel"
                    className="h-12"
                    placeholder="90 00 00 00"
                    defaultValue={farmer?.phone ?? ''}
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="gender">Sexe</Label>
                    <Select
                        name="gender"
                        defaultValue={farmer?.gender ?? undefined}
                    >
                        <SelectTrigger id="gender" className="h-12 w-full">
                            <SelectValue placeholder="Non renseigné" />
                        </SelectTrigger>
                        <SelectContent>
                            {genders.map((gender) => (
                                <SelectItem
                                    key={gender.value}
                                    value={gender.value}
                                >
                                    {gender.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="birth_year">Année de naissance</Label>
                    <Input
                        id="birth_year"
                        name="birth_year"
                        type="number"
                        inputMode="numeric"
                        className="h-12"
                        placeholder="1985"
                        defaultValue={farmer?.birth_year ?? ''}
                    />
                    <InputError message={errors.birth_year} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="experience_years">
                        Années d'expérience
                    </Label>
                    <Input
                        id="experience_years"
                        name="experience_years"
                        type="number"
                        inputMode="numeric"
                        className="h-12"
                        placeholder="12"
                        defaultValue={farmer?.experience_years ?? ''}
                    />
                    <InputError message={errors.experience_years} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="user_id">Compte de consultation</Label>
                <Select
                    name="user_id"
                    defaultValue={
                        farmer?.user_id === null ||
                        farmer?.user_id === undefined
                            ? NO_ACCOUNT
                            : String(farmer.user_id)
                    }
                >
                    <SelectTrigger id="user_id" className="h-12 w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_ACCOUNT}>Aucun compte</SelectItem>
                        {availableAccounts.map((account) => (
                            <SelectItem
                                key={account.id}
                                value={String(account.id)}
                            >
                                {account.name} · {account.phone}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                    À renseigner si le producteur consulte lui-même
                    l'application.
                </p>
                <InputError message={errors.user_id} />
            </div>
        </div>
    );
}

/**
 * The "no account" placeholder is not a value the server knows about.
 */
export function normalizeFarmerPayload(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        user_id: data.user_id === NO_ACCOUNT ? null : data.user_id,
    };
}
