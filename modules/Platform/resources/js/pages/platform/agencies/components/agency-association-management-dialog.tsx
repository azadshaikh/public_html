import { useId } from 'react';
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
import { MultiSelectCombobox } from '@/components/ui/multi-select-combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

export type ManagementOption = {
    value: number;
    label: string;
    description?: string;
};

const NO_PRIMARY_VALUE = '__none__';

export function mergeManagementOptions(
    current: ManagementOption[],
    available: ManagementOption[],
): ManagementOption[] {
    const optionMap = new Map<number, ManagementOption>();

    [...current, ...available].forEach((option) => {
        optionMap.set(option.value, option);
    });

    return Array.from(optionMap.values()).sort((left, right) =>
        left.label.localeCompare(right.label),
    );
}

export function AssociationManagementDialog({
    open,
    onOpenChange,
    title,
    description,
    selectionLabel,
    selectionHelp,
    primaryLabel,
    primaryHelp,
    options,
    selectedValues,
    onSelectedValuesChange,
    primaryValue,
    onPrimaryValueChange,
    loading,
    saving,
    onSave,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    selectionLabel: string;
    selectionHelp: string;
    primaryLabel: string;
    primaryHelp: string;
    options: ManagementOption[];
    selectedValues: number[];
    onSelectedValuesChange: (value: number[]) => void;
    primaryValue: string;
    onPrimaryValueChange: (value: string) => void;
    loading: boolean;
    saving: boolean;
    onSave: () => void;
}) {
    const selectionId = useId();
    const selectedOptions = options.filter((option) =>
        selectedValues.includes(option.value),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor={selectionId}>{selectionLabel}</Label>
                        <MultiSelectCombobox
                            id={selectionId}
                            value={selectedValues}
                            options={options}
                            onValueChange={onSelectedValuesChange}
                            disabled={saving}
                            placeholder={
                                loading ? 'Loading options...' : 'Select options'
                            }
                            emptyMessage={
                                loading ? 'Loading options...' : 'No options found.'
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            {selectionHelp}
                        </p>
                        {loading ? (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Loading available options...
                            </div>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label>{primaryLabel}</Label>
                        <Select
                            value={primaryValue || NO_PRIMARY_VALUE}
                            onValueChange={(value) =>
                                onPrimaryValueChange(
                                    value === NO_PRIMARY_VALUE ? '' : value,
                                )
                            }
                            disabled={saving || selectedOptions.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_PRIMARY_VALUE}>None</SelectItem>
                                {selectedOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-sm text-muted-foreground">
                            {primaryHelp}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button type="button" onClick={onSave} disabled={saving}>
                        {saving ? (
                            <>
                                <Spinner className="mr-2" />
                                Saving...
                            </>
                        ) : (
                            'Save Changes'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
