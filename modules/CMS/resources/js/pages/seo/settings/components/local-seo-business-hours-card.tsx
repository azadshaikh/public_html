import { Clock3Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import type {
    LocalSeoFormBindings,
    LocalSeoOption,
} from './local-seo-form-shared';

type LocalSeoBusinessHoursCardProps = {
    form: LocalSeoFormBindings;
    rows: number;
    openingDayOptions: LocalSeoOption[];
    onAddHourRow: () => void;
    onRemoveHourRow: (index: number) => void;
};

export function LocalSeoBusinessHoursCard({
    form,
    rows,
    openingDayOptions,
    onAddHourRow,
    onRemoveHourRow,
}: LocalSeoBusinessHoursCardProps) {
    const updateArrayField = (
        field: 'opening_hour_day' | 'opening_hours' | 'closing_hours',
        index: number,
        value: string,
    ) => {
        const nextValues = [...form.values[field]];
        nextValues[index] = value;
        form.setField(field, nextValues);
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <Clock3Icon className="size-4 text-muted-foreground" />
                        <CardTitle>Business hours</CardTitle>
                    </div>
                    <Field orientation="horizontal">
                        <Switch
                            checked={form.values.is_opening_hour_24_7}
                            onCheckedChange={(checked) =>
                                form.setField('is_opening_hour_24_7', checked)
                            }
                        />
                        <FieldLabel>Open 24/7</FieldLabel>
                    </Field>
                </div>
                <CardDescription>
                    Add one or more opening windows. Split shifts can be
                    represented with multiple rows.
                </CardDescription>
            </CardHeader>
            {!form.values.is_opening_hour_24_7 ? (
                <CardContent className="flex flex-col gap-4">
                    {Array.from({ length: rows }).map((_, index) => (
                        <div
                            key={`hour-row-${index}`}
                            className="grid gap-3 rounded-xl border p-4 md:grid-cols-[1.2fr_1fr_1fr_auto]"
                        >
                            <Field>
                                <FieldLabel htmlFor={`opening-day-${index}`}>
                                    Day
                                </FieldLabel>
                                <NativeSelect
                                    id={`opening-day-${index}`}
                                    className="w-full"
                                    value={
                                        form.values.opening_hour_day[index] ??
                                        ''
                                    }
                                    onChange={(event) =>
                                        updateArrayField(
                                            'opening_hour_day',
                                            index,
                                            event.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Select day
                                    </NativeSelectOption>
                                    {openingDayOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={`opening-hours-${index}`}>
                                    Opens
                                </FieldLabel>
                                <Input
                                    id={`opening-hours-${index}`}
                                    type="time"
                                    value={
                                        form.values.opening_hours[index] ?? ''
                                    }
                                    onChange={(event) =>
                                        updateArrayField(
                                            'opening_hours',
                                            index,
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={`closing-hours-${index}`}>
                                    Closes
                                </FieldLabel>
                                <Input
                                    id={`closing-hours-${index}`}
                                    type="time"
                                    value={
                                        form.values.closing_hours[index] ?? ''
                                    }
                                    onChange={(event) =>
                                        updateArrayField(
                                            'closing_hours',
                                            index,
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="flex items-end">
                                <Button
                                    type="button"
                                    variant={
                                        index === 0
                                            ? 'outline'
                                            : 'destructive'
                                    }
                                    onClick={() =>
                                        index === 0
                                            ? onAddHourRow()
                                            : onRemoveHourRow(index)
                                    }
                                >
                                    {index === 0 ? 'Add row' : 'Remove'}
                                </Button>
                            </div>
                        </div>
                    ))}
                </CardContent>
            ) : null}
        </Card>
    );
}
