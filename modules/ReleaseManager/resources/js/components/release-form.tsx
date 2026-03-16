import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2Icon, SettingsIcon } from 'lucide-react';

type ReleaseFormData = {
    package_identifier: string;
    version: string;
    version_type: string;
    status: string;
    release_at: string;
    change_log: string;
    release_link: string;
    file_name: string;
    checksum: string;
    file_size: string | number;
};

interface ReleaseFormProps {
    initialValues: any;
    statusOptions: any[];
    versionTypes: any[];
    type: string;
    submitUrl: string;
    method: 'post' | 'put';
}

export function ReleaseForm({ initialValues, statusOptions, versionTypes, type, submitUrl, method }: ReleaseFormProps) {
    const routeNamespace = type === 'module' ? 'releasemanager.module' : 'releasemanager.application';
    const { data, setData, post, put, processing, errors } = useForm<ReleaseFormData>({
        package_identifier: initialValues.package_identifier || '',
        version: initialValues.version || '',
        version_type: initialValues.version_type || 'minor',
        status: initialValues.status || 'draft',
        release_at: initialValues.release_at || '',
        change_log: initialValues.change_log || '',
        release_link: initialValues.release_link || '',
        file_name: initialValues.file_name || '',
        checksum: initialValues.checksum || '',
        file_size: initialValues.file_size || '',
    });

    const [isGeneratingVersion, setIsGeneratingVersion] = useState(false);

    const generateNextVersion = async () => {
        setIsGeneratingVersion(true);

        try {
            const packageIdentifier = type === 'application' ? 'main' : data.package_identifier;
            const response = await fetch(route(`${routeNamespace}.next-version`, {
                versionType: data.version_type,
                package_identifier: packageIdentifier,
            }), {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (! response.ok) {
                throw new Error(`Unable to generate version (${response.status})`);
            }

            const result = await response.json();

            if (result?.version) {
                setData('version', result.version);
            }
        } catch (error) {
            console.error('Failed to generate next version', error);
        } finally {
            setIsGeneratingVersion(false);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (method === 'post') {
            post(submitUrl);
        } else {
            put(submitUrl);
        }
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="flex flex-col gap-6 lg:col-span-2">
                <Card>
                    <CardContent className="flex flex-col gap-6 pt-6">
                        {type === 'module' && (
                            <div className="space-y-2">
                                <Label htmlFor="package_identifier">Package Identifier <span className="text-destructive">*</span></Label>
                                <Input
                                    id="package_identifier"
                                    value={data.package_identifier}
                                    onChange={(e) => setData('package_identifier', e.target.value)}
                                    placeholder="e.g. acme/module-name"
                                />
                                {errors.package_identifier && <p className="text-sm text-destructive">{errors.package_identifier}</p>}
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="version_type">Version Type</Label>
                                <Select value={data.version_type} onValueChange={(val) => setData('version_type', val)}>
                                    <SelectTrigger id="version_type">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {versionTypes.map((vt) => (
                                            <SelectItem key={vt.value} value={vt.value}>
                                                {vt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.version_type && <p className="text-sm text-destructive">{errors.version_type}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="version">Version <span className="text-destructive">*</span></Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="version"
                                        value={data.version}
                                        onChange={(e) => setData('version', e.target.value)}
                                        placeholder="e.g. 1.0.0"
                                    />
                                    <Button type="button" variant="outline" onClick={generateNextVersion} disabled={isGeneratingVersion}>
                                        {isGeneratingVersion ? <Loader2Icon className="animate-spin size-4" /> : <SettingsIcon className="size-4" />}
                                    </Button>
                                </div>
                                {errors.version && <p className="text-sm text-destructive">{errors.version}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="release_link">Release Link <span className="text-destructive">*</span></Label>
                            <Input
                                id="release_link"
                                value={data.release_link}
                                onChange={(e) => setData('release_link', e.target.value)}
                                placeholder="https://..."
                            />
                            {errors.release_link && <p className="text-sm text-destructive">{errors.release_link}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="change_log">Change Log</Label>
                            <Textarea
                                id="change_log"
                                value={data.change_log}
                                onChange={(e) => setData('change_log', e.target.value)}
                                placeholder="Markdown supported details about this release..."
                                rows={8}
                            />
                            {errors.change_log && <p className="text-sm text-destructive">{errors.change_log}</p>}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-6 pt-6">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="file_name">Download File Name</Label>
                                <Input
                                    id="file_name"
                                    value={data.file_name}
                                    onChange={(e) => setData('file_name', e.target.value)}
                                    placeholder="e.g. release-1.0.0.zip"
                                />
                                {errors.file_name && <p className="text-sm text-destructive">{errors.file_name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="file_size">File Size (bytes)</Label>
                                <Input
                                    id="file_size"
                                    type="number"
                                    value={data.file_size}
                                    onChange={(e) => setData('file_size', e.target.value)}
                                />
                                {errors.file_size && <p className="text-sm text-destructive">{errors.file_size}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="checksum">Checksum (SHA256)</Label>
                            <Input
                                id="checksum"
                                value={data.checksum}
                                onChange={(e) => setData('checksum', e.target.value)}
                            />
                            {errors.checksum && <p className="text-sm text-destructive">{errors.checksum}</p>}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="flex flex-col gap-6 pt-6">
                        <div className="space-y-2">
                            <Label htmlFor="status">Status</Label>
                            <Select value={data.status} onValueChange={(val) => setData('status', val)}>
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((st) => (
                                        <SelectItem key={st.value} value={st.value}>
                                            {st.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.status && <p className="text-sm text-destructive">{errors.status}</p>}
                        </div>

                        <div className="flex w-full flex-col items-start space-y-2">
                            <Label htmlFor="release_at">Release Date</Label>
                            <Input
                                id="release_at"
                                type="date"
                                value={data.release_at}
                                onChange={(event) => setData('release_at', event.target.value)}
                            />
                            {errors.release_at && <p className="text-sm text-destructive">{errors.release_at}</p>}
                        </div>

                        <Button type="submit" disabled={processing} className="w-full">
                            {processing && <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />}
                            {method === 'post' ? 'Create Release' : 'Save Changes'}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </form>
    );
}
