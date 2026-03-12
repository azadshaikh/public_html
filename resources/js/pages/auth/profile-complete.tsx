import { Form } from '@inertiajs/react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/profile/complete';

type Props = {
    user: {
        first_name: string | null;
        last_name: string | null;
        email: string | null;
    };
};

export default function ProfileComplete({ user }: Props) {
    return (
        <AuthLayout
            title="Complete your profile"
            description="Add your name to finish setting up your account."
        >
            <AppHead
                title="Complete profile"
                description="Finish setting up your account by adding your name."
            />

            <div className="space-y-6">
                <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                    <p>
                        Signed in as{' '}
                        <span className="font-medium text-foreground">
                            {user.email ?? 'your account'}
                        </span>
                        .
                    </p>
                </div>

                <Form
                    {...store.form()}
                    className="flex flex-col gap-6"
                    resetOnSuccess={[]}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">
                                        First name
                                    </Label>
                                    <Input
                                        id="first_name"
                                        name="first_name"
                                        defaultValue={user.first_name ?? ''}
                                        autoComplete="given-name"
                                        autoFocus
                                        placeholder="First name"
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        defaultValue={user.last_name ?? ''}
                                        autoComplete="family-name"
                                        placeholder="Last name"
                                    />
                                    <InputError message={errors.last_name} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Continue to dashboard
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
