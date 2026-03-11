import { Form, Head, Link, router } from '@inertiajs/react';
import {
    BotIcon,
    LayoutDashboardIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    SparklesIcon,
    Trash2Icon,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string };

type ModuleMeta = {
    name: string;
    slug: string;
    version: string;
    description: string;
};

type PromptListItem = {
    id: number;
    name: string;
    slug: string;
    purpose: string;
    model: string;
    tone: string;
    status: string;
    is_default: boolean;
};

type PaginatedData<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    from: number | null;
    to: number | null;
};

type ChatBotIndexPageProps = {
    module: ModuleMeta;
    filters: { search: string; status: string };
    prompts: PaginatedData<PromptListItem>;
    stats: { total: number; active: number; draft: number; defaults: number };
    options: { statusOptions: Option[]; toneOptions: Option[] };
    status?: string;
};

export default function ChatBotIndex({
    module,
    filters,
    prompts,
    stats,
    options,
    status,
}: ChatBotIndexPageProps) {
    const chatbotIndexUrl = '/chatbot';
    const chatbotCreateUrl = '/chatbot/create';
    const chatbotEditUrl = (id: number) => `/chatbot/${id}/edit`;
    const chatbotDestroyUrl = (id: number) => `/chatbot/${id}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: module.name, href: chatbotIndexUrl },
    ];

    const handleDelete = (prompt: PromptListItem) => {
        if (!window.confirm(`Delete ${prompt.name}?`)) {
            return;
        }

        router.delete(chatbotDestroyUrl(prompt.id), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`${module.name} prompts`}
            description="Manage reusable prompt templates from the module shell."
            headerActions={
                <div className="flex flex-wrap gap-3">
                    <Button asChild variant="outline">
                        <Link href={dashboard()}>
                            <LayoutDashboardIcon />
                            Back to dashboard
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={chatbotCreateUrl}>
                            <PlusIcon />
                            Create prompt
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title={`${module.name} prompts`} />

            <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
                    <CardHeader>
                        <Badge
                            variant="secondary"
                            className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15"
                        >
                            Starter CRUD
                        </Badge>
                        <CardTitle className="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">
                            Turn prompt ideas into reusable assistant building
                            blocks.
                        </CardTitle>
                        <CardDescription className="text-primary-foreground/75">
                            Prompt templates are a good starting point for
                            assistants, support flows, escalation agents, and
                            knowledge-powered responses.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <div className="text-sm text-primary-foreground/70">
                                Templates
                            </div>
                            <div className="mt-1 text-xl font-semibold">
                                {stats.total}
                            </div>
                        </div>
                        <div>
                            <div className="text-sm text-primary-foreground/70">
                                Active
                            </div>
                            <div className="mt-1 text-xl font-semibold">
                                {stats.active}
                            </div>
                        </div>
                        <div>
                            <div className="text-sm text-primary-foreground/70">
                                Defaults
                            </div>
                            <div className="mt-1 text-xl font-semibold">
                                {stats.defaults}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Suggested extensions</CardTitle>
                        <CardDescription>
                            Start simple, then grow into assistant
                            orchestration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {[
                            'Version prompt strategies by tone or model',
                            'Promote one template as the default assistant baseline',
                            'Add future testing, analytics, and prompt version history',
                        ].map((item) => (
                            <div
                                key={item}
                                className="rounded-xl border bg-muted/40 p-4"
                            >
                                <div className="mb-2 flex items-center gap-2 font-medium">
                                    <SparklesIcon className="size-4 text-primary" />
                                    Starter idea
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {item}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </section>

            {status ? (
                <Alert className="mt-6">
                    <BotIcon className="size-4" />
                    <AlertTitle>Saved</AlertTitle>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            ) : null}

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Filter templates</CardTitle>
                    <CardDescription>
                        Search prompt names or narrow by rollout status.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action={chatbotIndexUrl}
                        method="get"
                        options={{ preserveScroll: true }}
                        className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]"
                    >
                        <InputGroup className="w-full">
                            <InputGroupAddon>
                                <SearchIcon />
                            </InputGroupAddon>
                            <InputGroupInput
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search name, slug, or purpose"
                            />
                        </InputGroup>

                        <NativeSelect
                            className="w-full"
                            name="status"
                            defaultValue={filters.status}
                        >
                            <NativeSelectOption value="">
                                All statuses
                            </NativeSelectOption>
                            {options.statusOptions.map((option) => (
                                <NativeSelectOption
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>

                        <div className="flex gap-2">
                            <Button type="submit">Apply</Button>
                            <Button asChild type="button" variant="outline">
                                <Link href={chatbotIndexUrl}>Reset</Link>
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <section className="mt-6 grid gap-4 lg:grid-cols-2">
                {prompts.data.length > 0 ? (
                    prompts.data.map((prompt) => (
                        <Card key={prompt.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-xl">
                                            {prompt.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {prompt.purpose}
                                        </CardDescription>
                                    </div>
                                    <div className="flex gap-2">
                                        <Badge variant="outline">
                                            {prompt.status}
                                        </Badge>
                                        {prompt.is_default ? (
                                            <Badge>Default</Badge>
                                        ) : null}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                    <Badge variant="secondary">
                                        {prompt.model}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {prompt.tone}
                                    </Badge>
                                    <Badge variant="secondary">
                                        /{prompt.slug}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={chatbotEditUrl(prompt.id)}>
                                            <PencilIcon />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => handleDelete(prompt)}
                                    >
                                        <Trash2Icon />
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                ) : (
                    <Card className="lg:col-span-2">
                        <CardContent className="p-6">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <BotIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No prompt templates yet
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Create a reusable system prompt to seed
                                        future assistants.
                                    </EmptyDescription>
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button asChild>
                                        <Link href={chatbotCreateUrl}>
                                            <PlusIcon />
                                            Create prompt
                                        </Link>
                                    </Button>
                                </EmptyContent>
                            </Empty>
                        </CardContent>
                    </Card>
                )}
            </section>

            {prompts.prev_page_url || prompts.next_page_url ? (
                <div className="mt-6 flex items-center justify-between gap-4 text-sm text-muted-foreground">
                    <span>
                        Showing {prompts.from ?? 0} to {prompts.to ?? 0} of{' '}
                        {prompts.total} templates
                    </span>
                    <div className="flex gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            disabled={!prompts.prev_page_url}
                        >
                            <Link
                                href={prompts.prev_page_url ?? chatbotIndexUrl}
                            >
                                Previous
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            disabled={!prompts.next_page_url}
                        >
                            <Link
                                href={prompts.next_page_url ?? chatbotIndexUrl}
                            >
                                Next
                            </Link>
                        </Button>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}
