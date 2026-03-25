import { GithubIcon, MailIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { FieldSeparator } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type SocialProvider = 'google' | 'github';

type AgencySocialAuthOptionsProps = {
    socialProviders: {
        google: boolean;
        github: boolean;
    };
    loadingProvider: SocialProvider | null;
    disabled: boolean;
    emailButtonLabel: string;
    onProviderClick: (provider: SocialProvider) => void;
    onEmailClick: () => void;
};

function ProviderIcon({ provider }: { provider: SocialProvider }) {
    if (provider === 'google') {
        return (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 48 48"
                aria-hidden="true"
                className="shrink-0"
            >
                <path
                    fill="#FFC107"
                    d="M43.611 20.083H42V20H24v8h11.303C33.655 32.657 29.195 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
                />
                <path
                    fill="#FF3D00"
                    d="M6.306 14.691 12.88 19.51A11.99 11.99 0 0 1 24 12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4c-7.682 0-14.353 4.337-17.694 10.691z"
                />
                <path
                    fill="#4CAF50"
                    d="M24 44c5.176 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.159 35.09 26.715 36 24 36c-5.175 0-9.628-3.327-11.286-7.946l-6.523 5.025C9.499 39.556 16.227 44 24 44z"
                />
                <path
                    fill="#1976D2"
                    d="M43.611 20.083H42V20H24v8h11.303a12.05 12.05 0 0 1-4.084 5.57l.003-.002 6.19 5.238C36.971 39.204 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
                />
            </svg>
        );
    }

    return <GithubIcon data-icon="inline-start" className="text-foreground" />;
}

export default function AgencySocialAuthOptions({
    socialProviders,
    loadingProvider,
    disabled,
    emailButtonLabel,
    onProviderClick,
    onEmailClick,
}: AgencySocialAuthOptionsProps) {
    const availableProviders: SocialProvider[] = [];

    if (socialProviders.google) {
        availableProviders.push('google');
    }

    if (socialProviders.github) {
        availableProviders.push('github');
    }

    return (
        <div className="flex flex-col gap-4">
            {availableProviders.map((provider) => {
                const isPrimary = provider === 'google' || !socialProviders.google;
                const label = provider === 'google' ? 'Continue with Google' : 'Continue with GitHub';

                return (
                    <Button
                        key={provider}
                        type="button"
                        variant={isPrimary ? 'default' : 'outline'}
                        size="xl"
                        className="w-full justify-center"
                        disabled={disabled}
                        onClick={() => onProviderClick(provider)}
                    >
                        {loadingProvider === provider ? (
                            <Spinner />
                        ) : (
                            <ProviderIcon provider={provider} />
                        )}
                        {label}
                    </Button>
                );
            })}

            {availableProviders.length > 0 ? (
                <FieldSeparator className="my-2.5">Or continue with</FieldSeparator>
            ) : null}

            <Button
                type="button"
                variant="secondary"
                size="xl"
                className={cn('w-full justify-center')}
                disabled={disabled}
                onClick={onEmailClick}
            >
                <MailIcon data-icon="inline-start" />
                {emailButtonLabel}
            </Button>
        </div>
    );
}