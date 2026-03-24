import { Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, SettingsIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type {
    ChatBotGeneralSettings,
    ChatBotProviderSettings,
    ChatBotSettingsPageProps,
    ChatBotToolSettings,
    ToolGroup,
} from '../../../types/chatbot';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'ChatBot', href: route('app.chatbot.index') },
    { title: 'Settings', href: route('app.chatbot.settings.index', { section: 'general' }) },
];

const SECTIONS = [
    { key: 'general', label: 'General' },
    { key: 'provider', label: 'AI Provider' },
    { key: 'tools', label: 'Tools' },
] as const;

// ─── General section ──────────────────────────────────────────────────────────

function GeneralSection({ initialValues }: { initialValues: ChatBotGeneralSettings }) {
    const form = useAppForm<ChatBotGeneralSettings>({
        rememberKey: 'chatbot.settings.general',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            chatbot_system_prompt: [formValidators.maxLength('System prompt', 5000)],
            chatbot_chat_title: [formValidators.maxLength('Chat title', 100)],
            chatbot_placeholder: [formValidators.maxLength('Placeholder', 200)],
            chatbot_max_tool_steps: [formValidators.required('Max tool steps')],
        },
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        form.submit('post', route('app.chatbot.settings.update-general'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'General settings updated',
                description: 'Your ChatBot general settings have been saved.',
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SettingsIcon data-icon="inline-start" />
                        General Settings
                    </CardTitle>
                    <CardDescription>
                        Configure system prompt, appearance, and behaviour.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Field data-invalid={form.invalid('chatbot_system_prompt') || undefined}>
                        <FieldLabel htmlFor="chatbot_system_prompt">System Prompt</FieldLabel>
                        <Textarea
                            id="chatbot_system_prompt"
                            rows={5}
                            value={form.data.chatbot_system_prompt}
                            onChange={(e) => form.setField('chatbot_system_prompt', e.target.value)}
                            onBlur={() => form.touch('chatbot_system_prompt')}
                            aria-invalid={form.invalid('chatbot_system_prompt') || undefined}
                        />
                        <FieldError>{form.error('chatbot_system_prompt')}</FieldError>
                    </Field>

                    <FieldGroup className="md:grid-cols-2">
                        <Field data-invalid={form.invalid('chatbot_chat_title') || undefined}>
                            <FieldLabel htmlFor="chatbot_chat_title">Chat Title</FieldLabel>
                            <Input
                                id="chatbot_chat_title"
                                value={form.data.chatbot_chat_title}
                                onChange={(e) => form.setField('chatbot_chat_title', e.target.value)}
                                onBlur={() => form.touch('chatbot_chat_title')}
                                aria-invalid={form.invalid('chatbot_chat_title') || undefined}
                            />
                            <FieldError>{form.error('chatbot_chat_title')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('chatbot_placeholder') || undefined}>
                            <FieldLabel htmlFor="chatbot_placeholder">Input Placeholder</FieldLabel>
                            <Input
                                id="chatbot_placeholder"
                                value={form.data.chatbot_placeholder}
                                onChange={(e) => form.setField('chatbot_placeholder', e.target.value)}
                                onBlur={() => form.touch('chatbot_placeholder')}
                                aria-invalid={form.invalid('chatbot_placeholder') || undefined}
                            />
                            <FieldError>{form.error('chatbot_placeholder')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('chatbot_max_tool_steps') || undefined}>
                            <FieldLabel htmlFor="chatbot_max_tool_steps">
                                Max Tool Steps{' '}
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="chatbot_max_tool_steps"
                                type="number"
                                min={1}
                                max={100}
                                value={form.data.chatbot_max_tool_steps}
                                onChange={(e) =>
                                    form.setField('chatbot_max_tool_steps', Number(e.target.value))
                                }
                                onBlur={() => form.touch('chatbot_max_tool_steps')}
                                aria-invalid={form.invalid('chatbot_max_tool_steps') || undefined}
                            />
                            <FieldDescription>
                                Maximum number of tool invocations before stopping (1–100).
                            </FieldDescription>
                            <FieldError>{form.error('chatbot_max_tool_steps')}</FieldError>
                        </Field>

                        <Field>
                            <div className="flex items-center gap-2 pt-6">
                                <Checkbox
                                    id="chatbot_show_thinking"
                                    checked={form.data.chatbot_show_thinking}
                                    onCheckedChange={(checked) =>
                                        form.setField('chatbot_show_thinking', Boolean(checked))
                                    }
                                />
                                <FieldLabel htmlFor="chatbot_show_thinking" className="cursor-pointer">
                                    Show thinking / reasoning blocks
                                </FieldLabel>
                            </div>
                        </Field>
                    </FieldGroup>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save General Settings'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}

// ─── Provider section ─────────────────────────────────────────────────────────

function ProviderSection({
    initialValues,
    providerOptions,
}: {
    initialValues: ChatBotProviderSettings;
    providerOptions: Array<{ value: string; label: string }>;
}) {
    const form = useAppForm<ChatBotProviderSettings>({
        rememberKey: 'chatbot.settings.provider',
        defaults: initialValues,
        dontRemember: ['chatbot_api_key'],
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        form.submit('post', route('app.chatbot.settings.update-provider'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Provider settings updated',
                description: 'Your AI provider configuration has been saved.',
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SettingsIcon data-icon="inline-start" />
                        AI Provider
                    </CardTitle>
                    <CardDescription>
                        Choose which AI provider and model to use.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <FieldGroup className="md:grid-cols-2">
                        <Field data-invalid={form.invalid('chatbot_provider') || undefined}>
                            <FieldLabel htmlFor="chatbot_provider">Provider</FieldLabel>
                            <NativeSelect
                                id="chatbot_provider"
                                value={form.data.chatbot_provider}
                                onChange={(e) => form.setField('chatbot_provider', e.target.value)}
                                onBlur={() => form.touch('chatbot_provider')}
                                aria-invalid={form.invalid('chatbot_provider') || undefined}
                            >
                                {providerOptions.map((opt) => (
                                    <NativeSelectOption key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <FieldError>{form.error('chatbot_provider')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('chatbot_model') || undefined}>
                            <FieldLabel htmlFor="chatbot_model">Model</FieldLabel>
                            <Input
                                id="chatbot_model"
                                placeholder="e.g. gpt-4o, claude-3-5-sonnet-latest"
                                value={form.data.chatbot_model}
                                onChange={(e) => form.setField('chatbot_model', e.target.value)}
                                onBlur={() => form.touch('chatbot_model')}
                                aria-invalid={form.invalid('chatbot_model') || undefined}
                            />
                            <FieldDescription>
                                Leave blank to use the provider's default model.
                            </FieldDescription>
                            <FieldError>{form.error('chatbot_model')}</FieldError>
                        </Field>

                        <Field
                            data-invalid={form.invalid('chatbot_api_key') || undefined}
                            className="md:col-span-2"
                        >
                            <FieldLabel htmlFor="chatbot_api_key">API Key</FieldLabel>
                            <Input
                                id="chatbot_api_key"
                                type="password"
                                placeholder="Leave blank to keep the current key"
                                value={form.data.chatbot_api_key}
                                onChange={(e) => form.setField('chatbot_api_key', e.target.value)}
                                onBlur={() => form.touch('chatbot_api_key')}
                                aria-invalid={form.invalid('chatbot_api_key') || undefined}
                                autoComplete="new-password"
                            />
                            <FieldDescription>
                                Only enter a value to update the stored key. Leave blank to keep the current one.
                            </FieldDescription>
                            <FieldError>{form.error('chatbot_api_key')}</FieldError>
                        </Field>
                    </FieldGroup>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save Provider Settings'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}

// ─── Tools section ────────────────────────────────────────────────────────────

function ToolsSection({
    initialValues,
    toolGroups,
}: {
    initialValues: ChatBotToolSettings;
    toolGroups: ToolGroup[];
}) {
    const form = useAppForm<ChatBotToolSettings>({
        rememberKey: 'chatbot.settings.tools',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        form.submit('post', route('app.chatbot.settings.update-tools'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Tool settings updated',
                description: 'Your tool enablement settings have been saved.',
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SettingsIcon data-icon="inline-start" />
                        Tool Permissions
                    </CardTitle>
                    <CardDescription>
                        Enable or disable individual tools the AI can use.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    {toolGroups.map((group) => (
                        <div key={group.title}>
                            <h3 className="mb-3 text-sm font-medium text-muted-foreground">
                                {group.title}
                            </h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {group.tools.map((tool) => (
                                    <div
                                        key={tool.key}
                                        className="flex items-start gap-3 rounded-lg border p-3"
                                    >
                                        <Checkbox
                                            id={`tool-${tool.key}`}
                                            checked={Boolean(form.data[tool.key])}
                                            onCheckedChange={(checked) =>
                                                form.setField(tool.key, Boolean(checked))
                                            }
                                            className="mt-0.5 shrink-0"
                                        />
                                        <div className="min-w-0">
                                            <label
                                                htmlFor={`tool-${tool.key}`}
                                                className="cursor-pointer text-sm font-medium"
                                            >
                                                {tool.label}
                                            </label>
                                            <p className="text-xs text-muted-foreground">
                                                {tool.description}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save Tool Settings'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function ChatBotSettings({
    section,
    initialValues,
    providerOptions,
    toolGroups,
}: ChatBotSettingsPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="ChatBot Settings"
            description="Configure AI provider, system prompt, and tool access"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.chatbot.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Chat
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto w-full max-w-3xl space-y-6">
                {/* Tab navigation */}
                <div className="flex gap-1 rounded-lg border bg-muted/30 p-1">
                    {SECTIONS.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() =>
                                router.get(
                                    route('app.chatbot.settings.index', { section: key }),
                                    {},
                                    { preserveScroll: true, preserveState: true },
                                )
                            }
                            className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                section === key
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {/* Section content */}
                {section === 'general' && (
                    <GeneralSection initialValues={initialValues.general} />
                )}

                {section === 'provider' && (
                    <ProviderSection
                        initialValues={initialValues.provider}
                        providerOptions={providerOptions}
                    />
                )}

                {section === 'tools' && (
                    <ToolsSection
                        initialValues={initialValues.tools}
                        toolGroups={toolGroups}
                    />
                )}
            </div>
        </AppLayout>
    );
}
