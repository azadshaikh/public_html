import { useHttp } from '@inertiajs/react';
import {
    FileCode2Icon,
    RefreshCwIcon,
    SearchIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
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
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import {
    extractHttpErrorMessage,
    LaravelToolsNavigation,
    flattenConfigEntries,
    getLaravelToolsBreadcrumbs,
} from '@/pages/masters/laravel-tools/components/shared';
import type {
    LaravelConfigValue,
    LaravelToolsConfigPageProps,
} from '@/types/laravel-tools';

export default function LaravelToolsConfig({
    configFiles,
    selectedFile,
    selectedConfig,
}: LaravelToolsConfigPageProps) {
    const [currentFile, setCurrentFile] = useState<string | null>(selectedFile);
    const [config, setConfig] = useState<LaravelConfigValue | null>(
        selectedConfig,
    );
    const [searchQuery, setSearchQuery] = useState('');
    const configRequest = useHttp<
        { file: string },
        {
            success: boolean;
            message?: string;
            config?: LaravelConfigValue;
        }
    >({
        file: selectedFile ?? '',
    });

    const configEntries = useMemo(
        () => (config === null ? [] : flattenConfigEntries(config)),
        [config],
    );

    const filteredEntries = useMemo(() => {
        if (searchQuery.trim() === '') {
            return configEntries;
        }

        const query = searchQuery.toLowerCase();

        return configEntries.filter(
            (entry) =>
                entry.key.toLowerCase().includes(query) ||
                entry.value.toLowerCase().includes(query),
        );
    }, [configEntries, searchQuery]);

    const maskedValueCount = useMemo(
        () => configEntries.filter((entry) => entry.masked).length,
        [configEntries],
    );

    const loadConfigFile = async (file: string) => {
        configRequest.transform(() => ({
            file,
        }));
        setCurrentFile(file);
        setSearchQuery('');

        try {
            const payload = await configRequest.get(
                route('app.masters.laravel-tools.config.values'),
            );

            if (!payload.success) {
                throw new Error(
                    payload.message || 'Unable to load configuration values.',
                );
            }

            setConfig(payload.config ?? null);
        } catch (error) {
            setConfig(null);
            showAppToast({
                variant: 'error',
                title: 'Load failed',
                description: extractHttpErrorMessage(
                    error,
                    'Unable to load configuration values.',
                ),
            });
        }
    };

    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs('Config Browser')}
            title="Config Browser"
            description="Review resolved Laravel configuration values with sensitive entries masked."
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation current="config" />

                <Alert>
                    <ShieldCheckIcon data-icon="inline-start" />
                    <AlertTitle>Sensitive values are masked</AlertTitle>
                    <AlertDescription>
                        Keys such as passwords, tokens, and API secrets are
                        replaced before rendering in the browser.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Configuration files</CardTitle>
                            <CardDescription>
                                Choose a file to inspect its current resolved
                                values.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScrollArea className="h-[38rem] pr-4">
                                <div className="flex flex-col gap-3">
                                    {configFiles.map((file) => {
                                        const isActive =
                                            currentFile === file.name;

                                        return (
                                            <button
                                                key={file.name}
                                                type="button"
                                                onClick={() =>
                                                    void loadConfigFile(
                                                        file.name,
                                                    )
                                                }
                                                className={`rounded-xl border p-4 text-left transition-colors ${
                                                    isActive
                                                        ? 'border-primary bg-primary/5'
                                                        : 'hover:bg-muted/50'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-muted text-foreground">
                                                        <FileCode2Icon />
                                                    </span>
                                                    <div className="flex min-w-0 flex-col gap-1">
                                                        <span className="truncate font-medium text-foreground">
                                                            {file.name}.php
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            config/{file.name}
                                                            .php
                                                        </span>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </ScrollArea>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex flex-col gap-1">
                                    <CardTitle>
                                        {currentFile
                                            ? `config/${currentFile}.php`
                                            : 'Select a config file'}
                                    </CardTitle>
                                    <CardDescription>
                                        Search across flattened dot-notation
                                        keys for faster inspection.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary">
                                        {configEntries.length} entries
                                    </Badge>
                                    <Badge variant="warning">
                                        {maskedValueCount} masked
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            currentFile
                                                ? void loadConfigFile(
                                                      currentFile,
                                                  )
                                                : undefined
                                        }
                                        disabled={
                                            currentFile === null ||
                                            configRequest.processing
                                        }
                                    >
                                        {configRequest.processing ? (
                                            <Spinner />
                                        ) : (
                                            <RefreshCwIcon data-icon="inline-start" />
                                        )}
                                        Refresh
                                    </Button>
                                </div>
                            </div>

                            <InputGroup className="w-full">
                                <InputGroupAddon>
                                    <SearchIcon />
                                </InputGroupAddon>
                                <InputGroupInput
                                    value={searchQuery}
                                    onChange={(event) =>
                                        setSearchQuery(event.target.value)
                                    }
                                    placeholder="Search keys or values..."
                                />
                            </InputGroup>
                        </CardHeader>
                        <CardContent>
                            {currentFile === null ? (
                                <Empty className="min-h-[30rem] border">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <FileCode2Icon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            Select a config file
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Pick a file from the left to load
                                            its resolved configuration values.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : configRequest.processing ? (
                                <div className="flex min-h-[30rem] items-center justify-center rounded-xl border">
                                    <Spinner />
                                </div>
                            ) : filteredEntries.length === 0 ? (
                                <Empty className="min-h-[30rem] border">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <SearchIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No matching keys
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Try a different search term or
                                            refresh the selected config file.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="overflow-hidden rounded-xl border">
                                    <ScrollArea className="h-[34rem]">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-[34%]">
                                                        Key
                                                    </TableHead>
                                                    <TableHead className="w-[12%]">
                                                        Type
                                                    </TableHead>
                                                    <TableHead>Value</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {filteredEntries.map(
                                                    (entry) => (
                                                        <TableRow
                                                            key={entry.key}
                                                        >
                                                            <TableCell className="align-top font-mono text-xs text-foreground">
                                                                {entry.key}
                                                            </TableCell>
                                                            <TableCell className="align-top">
                                                                <Badge
                                                                    variant={
                                                                        entry.masked
                                                                            ? 'warning'
                                                                            : 'outline'
                                                                    }
                                                                >
                                                                    {entry.type}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="align-top">
                                                                <span className="font-mono text-xs break-all text-muted-foreground">
                                                                    {
                                                                        entry.value
                                                                    }
                                                                </span>
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </ScrollArea>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
