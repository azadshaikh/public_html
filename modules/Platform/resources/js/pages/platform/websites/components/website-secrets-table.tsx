import { useHttp } from '@inertiajs/react';
import { ClipboardCopyIcon, EyeIcon, EyeOffIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import PasswordInput from '@/components/password-input';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { WebsiteSecretItem } from '../../../types/platform';

type WebsiteSecretsTableProps = {
    websiteId: number;
    secrets: WebsiteSecretItem[];
    canReveal: boolean;
};

export function WebsiteSecretsTable({ websiteId, secrets, canReveal }: WebsiteSecretsTableProps) {
    const [revealedValues, setRevealedValues] = useState<Record<number, string>>({});
    const [revealingId, setRevealingId] = useState<number | null>(null);
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [pendingSecretId, setPendingSecretId] = useState<number | null>(null);
    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const passwordInputRef = useRef<HTMLInputElement>(null);
    const revealRequest = useHttp<{ password: string }, { success?: boolean; value?: string }>({
        password: '',
    });

    function requestReveal(secretId: number) {
        if (revealedValues[secretId] !== undefined) {
            setRevealedValues((previous) => {
                const next = { ...previous };

                delete next[secretId];

                return next;
            });

            return;
        }

        setPendingSecretId(secretId);
        setPassword('');
        setPasswordError('');
        setPasswordModalOpen(true);
    }

    function handlePasswordSubmit() {
        if (!password.trim()) {
            setPasswordError('Password is required.');

            return;
        }

        if (pendingSecretId === null) {
            return;
        }

        const secretId = pendingSecretId;
        setRevealingId(secretId);

        void (async () => {
            try {
                revealRequest.transform(() => ({ password }));

                const payload = await revealRequest.post(
                    route('platform.websites.secrets.reveal', { website: websiteId, secret: secretId }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (typeof payload.value === 'string') {
                    setRevealedValues((previous) => ({ ...previous, [secretId]: payload.value }));
                }

                setPasswordModalOpen(false);
                setPassword('');
                setPasswordError('');
            } catch {
                setPasswordError('Incorrect password. Please try again.');
            } finally {
                setRevealingId(null);
            }
        })();
    }

    async function copyToClipboard(text: string) {
        await navigator.clipboard.writeText(text);
        showAppToast({ variant: 'success', title: 'Copied to clipboard!' });
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Key</th>
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Username</th>
                            <th className="pb-3 font-semibold text-muted-foreground">Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        {secrets.map((secret) => (
                            <tr key={secret.id} className="border-b last:border-0">
                                <td className="py-4 pr-4">
                                    <Badge variant="danger" className="font-mono">{secret.key}</Badge>
                                </td>
                                <td className="py-4 pr-4">
                                    {secret.username ? (
                                        <div className="flex items-center gap-2">
                                            <code className="rounded border bg-muted px-2 py-1 text-xs">{secret.username}</code>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={() => copyToClipboard(secret.username!)}
                                            >
                                                <ClipboardCopyIcon className="size-3.5" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </td>
                                <td className="py-4">
                                    <div className="flex items-center gap-2">
                                        <code className="rounded border bg-muted px-2 py-1 text-xs">
                                            {revealedValues[secret.id] ?? '••••••••'}
                                        </code>
                                        {canReveal ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                disabled={revealingId === secret.id}
                                                onClick={() => requestReveal(secret.id)}
                                            >
                                                {revealedValues[secret.id] !== undefined ? (
                                                    <EyeOffIcon className="size-3.5" />
                                                ) : (
                                                    <EyeIcon className="size-3.5" />
                                                )}
                                            </Button>
                                        ) : null}
                                        {revealedValues[secret.id] !== undefined ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={() => copyToClipboard(revealedValues[secret.id])}
                                            >
                                                <ClipboardCopyIcon className="size-3.5" />
                                            </Button>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Dialog open={passwordModalOpen} onOpenChange={setPasswordModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reveal Secret</DialogTitle>
                        <DialogDescription>
                            Enter your current account password to continue.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            handlePasswordSubmit();
                        }}
                    >
                        <div className="flex flex-col gap-2 py-4">
                            <Label htmlFor="reveal-password">Current Password</Label>
                            <PasswordInput
                                ref={passwordInputRef}
                                id="reveal-password"
                                placeholder="Enter current password"
                                value={password}
                                onChange={(event) => {
                                    setPassword(event.target.value);
                                    setPasswordError('');
                                }}
                                autoFocus
                            />
                            {passwordError ? (
                                <p className="text-sm text-destructive">{passwordError}</p>
                            ) : null}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPasswordModalOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={revealingId !== null}>
                                Continue
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}