import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import type {
    LocalSeoFormBindings,
    LocalSeoOption,
} from './local-seo-form-shared';

type LocalSeoStructuredDataCardProps = {
    form: LocalSeoFormBindings;
    businessTypeOptions: LocalSeoOption[];
    organizationMode: boolean;
};

export function LocalSeoStructuredDataCard({
    form,
    businessTypeOptions,
    organizationMode,
}: LocalSeoStructuredDataCardProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <CardTitle>Structured data</CardTitle>
                        <CardDescription>
                            Enable schema markup and choose whether this profile
                            represents an organisation or a person.
                        </CardDescription>
                    </div>
                    <Switch
                        checked={form.values.is_schema}
                        onCheckedChange={(checked) =>
                            form.setField('is_schema', checked)
                        }
                    />
                </div>
            </CardHeader>
            {form.values.is_schema ? (
                <CardContent className="flex flex-col gap-6">
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="type">Entity type</FieldLabel>
                            <NativeSelect
                                id="type"
                                className="w-full"
                                value={form.values.type}
                                onChange={(event) =>
                                    form.setField(
                                        'type',
                                        event.target.value as
                                            | 'Organization'
                                            | 'Person',
                                    )
                                }
                            >
                                <NativeSelectOption value="Organization">
                                    Organization / Business
                                </NativeSelectOption>
                                <NativeSelectOption value="Person">
                                    Person / Individual
                                </NativeSelectOption>
                            </NativeSelect>
                        </Field>

                        {organizationMode ? (
                            <Field>
                                <FieldLabel htmlFor="business_type">
                                    Business type
                                </FieldLabel>
                                <NativeSelect
                                    id="business_type"
                                    className="w-full"
                                    value={form.values.business_type}
                                    onChange={(event) =>
                                        form.setField(
                                            'business_type',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {businessTypeOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                        ) : null}
                    </FieldGroup>
                </CardContent>
            ) : null}
        </Card>
    );
}
