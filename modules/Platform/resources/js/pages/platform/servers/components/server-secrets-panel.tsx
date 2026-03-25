import { useHttp } from '@inertiajs/react';
import { ClipboardCopyIcon, EyeIcon, EyeOffIcon, KeyRoundIcon, ShieldIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import PasswordInput from '@/components/password-input';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { ServerSecretItem } from '../../../../types/platform';

type SpecialReveal = 'access-key' | 'ssh-key-pair';

type ServerSecretsPanelProps = {
    serverId: number;
    secrets: ServerSecretItem[];
    canReveal: boolean;
    canRevealSshKeyPair: boolean;
    accessKeyId: string | null;
    hasAccessKeySecret: boolean;
    hasSshCredentials: boolean;
};

export function ServerSecretsPanel({
    serverId,
    secrets,
    canReveal,
    canRevealSshKeyPair,
    accessKeyId,
    hasAccessKeySecret,
    hasSshCredentials,
}: ServerSecretsPanelProps) {
    const [revealedValues, setRevealedValues] = useState<Record<number, string>>({});
    const [revealedAccessKey, setRevealedAccessKey] = useState<string | null>(null);
    const [revealedSshKeyPair, setRevealedSshKeyPair] = useState<{
        publicKey: string | null;
        privateKey: string | null;
        authorizeCommand: string | null;
    } | null>(null);
    const [revealingId, setRevealingId] = useState<number | null>(null);
    const [revealingSpecial, setRevealingSpecial] = useState<SpecialReveal | null>(null);
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [pendingSecretId, setPendingSecretId] = useState<number | null>(null);
    const [pendingSpecial, setPendingSpecial] = useState<SpecialReveal | null>(null);
    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const passwordInputRef = useRef<HTMLInputElement>(null);
    const revealRequest = useHttp<{ password: string }, { success?: boolean; value?: string }>({ password: '' });
    const accessKeyRequest = useHttp<{ password: string }, { success?: boolean; value?: string }>({ password: '' });
    const sshKeyPairRequest = useHttp<{ password: string }, { success?: boolean; public_key?: string | null; private_key?: string | null; authorize_command?: string | null }>({ password: '' });

    function requestRevealSecret(secretId: number) {
        if (revealedValues[secretId] !== undefined) {
            setRevealedValues((previous) => {
                const next = { ...previous } as Record<number, string>;

                delete next[secretId];

                return next;
            });

            return;
        }

        setPendingSecretId(secretId);
        setPendingSpecial(null);
        setPassword('');
        setPasswordError('');
        setPasswordModalOpen(true);
    }

    function requestRevealSpecial(target: SpecialReveal) {
        if (target === 'access-key' && revealedAccessKey !== null) {
            setRevealedAccessKey(null);

            return;
        }

        if (target === 'ssh-key-pair' && revealedSshKeyPair !== null) {
            setRevealedSshKeyPair(null);

            return;
        }

        setPendingSecretId(null);
        setPendingSpecial(target);
        setPassword('');
        setPasswordError('');
        setPasswordModalOpen(true);
    }

    function handlePasswordSubmit() {
        if (!password.trim()) {
            setPasswordError('Password is required.');

            return;
        }

        if (pendingSecretId === null && pendingSpecial === null) {
            return;
        }

        if (pendingSecretId !== null) {
            const secretId = pendingSecretId;
            setRevealingId(secretId);

            void (async () => {
                try {
                    revealRequest.transform(() => ({ password }));

                    const payload = await revealRequest.post(
                        route('platform.servers.secrets.reveal', { server: serverId, secret: secretId }),
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    );

                    if (typeof payload.value === 'string') {
                        setRevealedValues((previous) => {
                            const next = {
                                ...previous,
                                [secretId]: payload.value,
                            } as Record<number, string>;

                            return next;
                        });
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

            return;
        }

        if (pendingSpecial === 'access-key') {
            setRevealingSpecial('access-key');

            void (async () => {
                try {
                    accessKeyRequest.transform(() => ({ password }));

                    const payload = await accessKeyRequest.post(
                        route('platform.servers.access-key-secret.reveal', { server: serverId }),
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    );

                    if (typeof payload.value === 'string') {
                        setRevealedAccessKey(payload.value);
                    }

                    setPasswordModalOpen(false);
                    setPassword('');
                    setPasswordError('');
                } catch {
                    setPasswordError('Incorrect password. Please try again.');
                } finally {
                    setRevealingSpecial(null);
                }
            })();

            return;
        }

        if (pendingSpecial === 'ssh-key-pair') {
            setRevealingSpecial('ssh-key-pair');

            void (async () => {
                try {
                    sshKeyPairRequest.transform(() => ({ password }));

                    const payload = await sshKeyPairRequest.post(
                        route('platform.servers.ssh-key-pair.reveal', { server: serverId }),
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    );

                    setRevealedSshKeyPair({
                        publicKey: payload.public_key ?? null,
                        privateKey: payload.private_key ?? null,
                        authorizeCommand: payload.authorize_command ?? null,
                    });

                    setPasswordModalOpen(false);
                    setPassword('');
                    setPasswordError('');
                } catch {
                    setPasswordError('Incorrect password. Please try again.');
                } finally {
                    setRevealingSpecial(null);
                }
            })();
        }
    }

    async function copyToClipboard(text: string) {
        await navigator.clipboard.writeText(text);
        showAppToast({ variant: 'success', title: 'Copied to clipboard!' });
    }

    const hasAccessKeyCredentials = accessKeyId !== null || hasAccessKeySecret;
    const hasAnySecrets = secrets.length > 0 || hasAccessKeyCredentials || hasSshCredentials;
    const revealedPrivateKey = revealedSshKeyPair?.privateKey ?? '';

    if (!hasAnySecrets) {
        return <p className="text-sm text-muted-foreground">No stored secrets available for this server.</p>;
    }

    return (
        <>
            <div className="flex flex-col gap-4">
                {(hasAccessKeyCredentials || (hasSshCredentials && canRevealSshKeyPair)) ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {hasAccessKeyCredentials ? (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <KeyRoundIcon className="size-4 text-muted-foreground" />
                                        <CardTitle className="text-base">Access Key Credentials</CardTitle>
                                    </div>
                                    <CardDescription>Copy the Hestia access ID directly or reveal the stored API secret for this server.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                        <p className="font-semibold text-foreground">Access ID</p>
                                        <div className="mt-2 flex items-center gap-2">
                                            <code className="block flex-1 break-all font-mono text-muted-foreground">
                                                {accessKeyId ?? '—'}
                                            </code>
                                            {accessKeyId ? (
                                                <Button variant="ghost" size="icon" className="size-7" onClick={() => void copyToClipboard(accessKeyId ?? '')}>
                                                    <ClipboardCopyIcon className="size-3.5" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                    <code className="block rounded border bg-muted px-3 py-2 text-xs break-all">
                                        {revealedAccessKey ?? '••••••••••••••••'}
                                    </code>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            disabled={!hasAccessKeySecret || !canReveal || revealingSpecial === 'access-key'}
                                            onClick={() => requestRevealSpecial('access-key')}
                                        >
                                            {revealedAccessKey ? <EyeOffIcon data-icon="inline-start" /> : <EyeIcon data-icon="inline-start" />}
                                            {revealedAccessKey ? 'Hide' : 'Reveal'}
                                        </Button>
                                        {revealedAccessKey ? (
                                            <Button variant="outline" onClick={() => void copyToClipboard(revealedAccessKey ?? '')}>
                                                <ClipboardCopyIcon data-icon="inline-start" />
                                                Copy
                                            </Button>
                                        ) : null}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}

                        {hasSshCredentials && canRevealSshKeyPair ? (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <ShieldIcon className="size-4 text-muted-foreground" />
                                        <CardTitle className="text-base">SSH Key Pair</CardTitle>
                                    </div>
                                    <CardDescription>Reveal the stored SSH public/private key pair used for provisioning.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                        <p className="font-semibold text-foreground">Private key</p>
                                        <p className="mt-2 break-all font-mono text-muted-foreground">
                                            {revealedSshKeyPair?.privateKey ?? '••••••••••••••••'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            disabled={revealingSpecial === 'ssh-key-pair'}
                                            onClick={() => requestRevealSpecial('ssh-key-pair')}
                                        >
                                            {revealedSshKeyPair ? <EyeOffIcon data-icon="inline-start" /> : <EyeIcon data-icon="inline-start" />}
                                            {revealedSshKeyPair ? 'Hide' : 'Reveal'}
                                        </Button>
                                        {revealedPrivateKey ? (
                                            <Button variant="outline" onClick={() => void copyToClipboard(revealedPrivateKey)}>
                                                <ClipboardCopyIcon data-icon="inline-start" />
                                                Copy
                                            </Button>
                                        ) : null}
                                    </div>
                                    {revealedSshKeyPair?.authorizeCommand ? (
                                        <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                            <p className="font-semibold text-foreground">Authorize command</p>
                                            <p className="mt-2 break-all font-mono text-muted-foreground">{revealedSshKeyPair.authorizeCommand}</p>
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                ) : null}

                {secrets.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="pb-3 pr-4 font-semibold text-muted-foreground">Key</th>
                                    <th className="pb-3 pr-4 font-semibold text-muted-foreground">Username</th>
                                    <th className="pb-3 font-semibold text-muted-foreground">Value</th>
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
                                                    <Button variant="ghost" size="icon" className="size-7" onClick={() => void copyToClipboard(secret.username ?? '')}>
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
                                                        onClick={() => requestRevealSecret(secret.id)}
                                                    >
                                                        {revealedValues[secret.id] !== undefined ? (
                                                            <EyeOffIcon className="size-3.5" />
                                                        ) : (
                                                            <EyeIcon className="size-3.5" />
                                                        )}
                                                    </Button>
                                                ) : null}
                                                {revealedValues[secret.id] !== undefined ? (
                                                    <Button variant="ghost" size="icon" className="size-7" onClick={() => void copyToClipboard(revealedValues[secret.id] ?? '')}>
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
                ) : null}
            </div>

            <Dialog open={passwordModalOpen} onOpenChange={setPasswordModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reveal Secret</DialogTitle>
                        <DialogDescription>Enter your current account password to continue.</DialogDescription>
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
                            {passwordError ? <p className="text-sm text-destructive">{passwordError}</p> : null}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setPasswordModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={revealingId !== null || revealingSpecial !== null}>
                                Continue
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
