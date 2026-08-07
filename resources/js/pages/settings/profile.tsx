import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profil" />

            <h1 className="sr-only">Paramètres du profil</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profil"
                    description="Mettez à jour votre nom et votre adresse e-mail"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nom</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block h-12 w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Nom complet"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    Numéro de téléphone
                                </Label>

                                <Input
                                    id="phone"
                                    type="tel"
                                    className="mt-1 block h-12 w-full"
                                    defaultValue={auth.user.phone}
                                    disabled
                                />

                                <p className="text-sm text-muted-foreground">
                                    Le numéro de téléphone sert d'identifiant de
                                    connexion. Contactez votre administrateur
                                    pour le modifier.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Adresse e-mail (facultative)
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block h-12 w-full"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    autoComplete="email"
                                    placeholder="adresse@exemple.com"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    className="h-12"
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Enregistrer
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profil',
            href: edit(),
        },
    ],
};
