import { ArrowLeftIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAppForm } from '@/hooks/use-app-form';

import type { PlatformOption, ServerFormValues } from '../../../../types/platform';
import { ServerWizardManualStep } from './server-wizard-manual-step';
import { ServerWizardModeStep } from './server-wizard-mode-step';
import { ServerWizardProvisionStep } from './server-wizard-provision-step';
import {
    csrfToken,
    INITIAL_CONNECTION_STATE,
    isWizardMode,
    parseJsonResponse,
} from './server-wizard-utils';
import type { ConnectionState, WizardStep } from './server-wizard-utils';

function getMissingConnectionRequirements(values: ServerFormValues): string[] {
    const missingRequirements: string[] = [];

    if (!values.ip.trim()) {
        missingRequirements.push('server IP address');
    }

    if (!values.ssh_private_key.trim()) {
        missingRequirements.push('SSH private key');
    }

    return missingRequirements;
}

type ServerCreateWizardProps = {
    initialValues: ServerFormValues;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    sshCommand?: string | null;
};

export default function ServerCreateWizard({
    initialValues,
    typeOptions,
    providerOptions,
    sshCommand,
}: ServerCreateWizardProps) {
    const form = useAppForm<ServerFormValues>({
        defaults: initialValues,
        rememberKey: 'platform.servers.create',
        dirtyGuard: true,
    });

    const [step, setStep] = useState<WizardStep>('mode');
    const [selectedMode, setSelectedMode] = useState<'manual' | 'provision' | null>(null);
    const [currentSshCommand, setCurrentSshCommand] = useState(sshCommand ?? '');
    const [copiedCommand, setCopiedCommand] = useState(false);
    const [regeneratingKey, setRegeneratingKey] = useState(false);
    const [wizardError, setWizardError] = useState<string | null>(null);
    const [connectionState, setConnectionState] = useState<ConnectionState>(
        INITIAL_CONNECTION_STATE,
    );

    useEffect(() => {
        setConnectionState(INITIAL_CONNECTION_STATE);
    }, [form.data.ip, form.data.ssh_port, form.data.ssh_private_key]);

    useEffect(() => {
        if (!isWizardMode(form.data.creation_mode)) {
            setSelectedMode(null);
            setStep('mode');

            return;
        }

        if (Object.keys(form.errors).length > 0) {
            setSelectedMode(form.data.creation_mode);
            setStep(form.data.creation_mode);
        }
    }, [form.data.creation_mode, form.errors]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('platform.servers.store'), {
            preserveScroll: true,
            successToast:
                form.data.creation_mode === 'manual'
                    ? 'Server connected successfully.'
                    : 'Provisioning started successfully.',
        });
    };

    const handleContinue = () => {
        if (!selectedMode) {
            return;
        }

        form.setField('creation_mode', selectedMode);
        setStep(selectedMode);
    };

    const handleBackToModeSelection = () => {
        if (isWizardMode(form.data.creation_mode)) {
            setSelectedMode(form.data.creation_mode);
        }

        setStep('mode');
    };

    const handleCopyCommand = async () => {
        if (!currentSshCommand.trim()) {
            return;
        }

        await navigator.clipboard.writeText(currentSshCommand);
        setCopiedCommand(true);

        window.setTimeout(() => {
            setCopiedCommand(false);
        }, 1200);
    };

    const handleRegenerateKey = async () => {
        setRegeneratingKey(true);
        setWizardError(null);

        try {
            const response = await fetch(route('platform.servers.generate-ssh-key'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            const payload = await parseJsonResponse<{
                public_key: string;
                private_key: string;
                command: string;
                message?: string;
            }>(response, 'Failed to generate SSH keys.');

            form.setField('ssh_public_key', payload.public_key);
            form.setField('ssh_private_key', payload.private_key);
            setCurrentSshCommand(payload.command);
            setConnectionState(INITIAL_CONNECTION_STATE);
        } catch (error) {
            setWizardError(
                error instanceof Error ? error.message : 'Failed to generate SSH keys.',
            );
        } finally {
            setRegeneratingKey(false);
        }
    };

    const handleVerifyConnection = async () => {
        const missingRequirements = getMissingConnectionRequirements(form.data);

        if (missingRequirements.length > 0) {
            setConnectionState({
                status: 'error',
                message: `Add ${missingRequirements.join(' and ')} before verifying the connection.`,
            });

            return;
        }

        setConnectionState({ status: 'loading', message: 'Connecting...' });

        try {
            const response = await fetch(route('platform.servers.verify-connection'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ip: form.data.ip,
                    ssh_port: form.data.ssh_port || '22',
                    ssh_private_key: form.data.ssh_private_key,
                }),
            });
            const payload = await parseJsonResponse<{
                message: string;
                os_info?: string | null;
            }>(response, 'Connection verification failed.');

            setConnectionState({
                status: 'success',
                message: payload.message,
                osInfo: payload.os_info,
            });
        } catch (error) {
            setConnectionState({
                status: 'error',
                message:
                    error instanceof Error
                        ? error.message
                        : 'Connection verification failed.',
            });
        }
    };

    return (
        <div className="flex flex-col gap-6">
            {form.dirtyGuardDialog}

            {step === 'mode' ? (
                <ServerWizardModeStep
                    selectedMode={selectedMode}
                    onSelectMode={setSelectedMode}
                    onContinue={handleContinue}
                />
            ) : (
                <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={handleBackToModeSelection}
                        >
                            <ArrowLeftIcon className="mr-2 size-4" />
                            Back to mode selection
                        </Button>
                    </div>

                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    {wizardError ? (
                        <Card className="border-destructive/50 bg-destructive/5">
                            <CardContent className="py-4 text-sm text-destructive">
                                {wizardError}
                            </CardContent>
                        </Card>
                    ) : null}

                    {step === 'manual' ? (
                        <ServerWizardManualStep
                            form={form}
                            typeOptions={typeOptions}
                            providerOptions={providerOptions}
                        />
                    ) : null}

                    {step === 'provision' ? (
                        <ServerWizardProvisionStep
                            form={form}
                            typeOptions={typeOptions}
                            providerOptions={providerOptions}
                            currentSshCommand={currentSshCommand}
                            copiedCommand={copiedCommand}
                            regeneratingKey={regeneratingKey}
                            connectionState={connectionState}
                            onCopyCommand={handleCopyCommand}
                            onRegenerateKey={handleRegenerateKey}
                            onVerifyConnection={handleVerifyConnection}
                        />
                    ) : null}
                </form>
            )}
        </div>
    );
}
