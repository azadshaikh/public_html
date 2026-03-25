import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { useAppForm } from '@/hooks/use-app-form';
import AuthCardLayout from '@/layouts/auth/auth-card-layout';

type ProtectedPost = {
    id: number;
    title: string | null;
    password_hint: string | null;
};

type PostAccessProtectionPageProps = {
    post: ProtectedPost;
};

export default function PostAccessProtectionForm({
    post,
}: PostAccessProtectionPageProps) {
    const form = useAppForm({
        defaults: {
            password: '',
        },
        rememberKey: `cms.post-access-protection.${post.id}`,
        dontRemember: ['password'],
    });

    const description =
        post.password_hint || 'Enter the password to view this protected post.';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('post.access.protection.verify', post.id), {
            setDefaultsOnSuccess: true,
        });
    };

    return (
        <AuthCardLayout
            title="Post Password Protected"
            description={description}
        >
            <AppHead
                title="Post Password Protected"
                description={description}
            />

            <div className="mb-6 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <span className="font-medium">Accessing protected content:</span>{' '}
                {post.title || 'Untitled post'}
            </div>

            <form
                onSubmit={handleSubmit}
                noValidate
                className="flex flex-col gap-6"
            >
                <Field data-invalid={form.invalid('password') || undefined}>
                    <FieldLabel htmlFor="password">
                        Enter post password
                    </FieldLabel>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autoFocus
                        autoComplete="current-password"
                        placeholder="Enter password to view content"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setField('password', event.target.value)
                        }
                        onBlur={() => form.touch('password')}
                        aria-invalid={form.invalid('password') || undefined}
                    />
                    <FieldError>{form.error('password')}</FieldError>
                </Field>

                <Button type="submit" size="xl" disabled={form.processing}>
                    {form.processing ? 'Verifying...' : 'Verify Password'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    This content is password-protected. Enter the correct password to continue.
                </p>
            </form>
        </AuthCardLayout>
    );
}
