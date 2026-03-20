import { XIcon } from 'lucide-react';
import { type ReactNode } from 'react';
import {
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Slider } from '@/components/ui/slider';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

export type SegmentedControlOption = {
    value: string;
    label: ReactNode;
    ariaLabel: string;
};

type StyleInputRowProps = {
    label: string;
    value: string | undefined;
    onChange: (value: string) => void;
    onClear?: () => void;
    placeholder?: string;
    suffix?: string;
    type?: string;
    containerClassName?: string;
    layout?: 'horizontal' | 'stacked';
};

type SliderStyleRowProps = {
    label: string;
    value: string | undefined;
    onChange: (value: string) => void;
    emptyLabel?: string;
    min: number;
    max: number;
    step?: number;
    displayValue?: (value: number, rawValue: string) => string;
    parseValue?: (value: string) => number;
    toValue?: (value: number) => string;
};

type InspectorSectionProps = {
    value: string;
    title: string;
    summary?: ReactNode;
    children: ReactNode;
};

export const FONT_WEIGHT_OPTIONS = [
    { value: '', label: 'auto' },
    { value: '100', label: 'Thin' },
    { value: '200', label: 'Extra Light' },
    { value: '300', label: 'Light' },
    { value: '400', label: 'Normal' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semi Bold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extra Bold' },
    { value: '900', label: 'Black' },
];

function normalizeStyleValue(value: string | undefined): string {
    return typeof value === 'string' ? value : '';
}

export function formatStyleValue(value: string | undefined, fallback = 'auto'): string {
    const trimmed = normalizeStyleValue(value).trim();

    return trimmed === '' ? fallback : trimmed;
}

export function formatDimensionValue(value: string | undefined): string {
    const trimmed = normalizeStyleValue(value).trim();

    return trimmed === '' ? 'auto' : trimmed;
}

export function formatLengthValue(value: string | undefined, fallback = 'auto'): string {
    const trimmed = normalizeStyleValue(value).trim();

    return trimmed === '' ? fallback : trimmed;
}

export function formatAlignmentLabel(value: string | undefined): string {
    switch (normalizeStyleValue(value)) {
        case 'center':
            return 'Center';
        case 'right':
            return 'Right';
        case 'justify':
            return 'Justify';
        case 'left':
            return 'Left';
        default:
            return 'auto';
    }
}

export function parseNumericValue(value: string | undefined, fallback = 0): number {
    const numeric = Number.parseFloat(normalizeStyleValue(value));

    return Number.isFinite(numeric) ? numeric : fallback;
}

export function formatSpacingSummary(top: string | undefined, right: string | undefined, bottom: string | undefined, left: string | undefined): string {
    const values = [top, right, bottom, left];

    if (values.every((value) => normalizeStyleValue(value).trim() === '')) {
        return 'auto';
    }

    return [top, right, bottom, left]
        .map((value) => formatLengthValue(value, 'auto'))
        .join(' ');
}

export function formatSliderNumber(value: number, digits = 2): string {
    return String(Number(value.toFixed(digits)));
}

function parseLengthValue(value: string | undefined): number {
    const match = normalizeStyleValue(value).match(/-?\d+(?:\.\d+)?/);

    return match ? Number.parseFloat(match[0]) : 0;
}

function parseOpacityValue(value: string | undefined): number {
    const numeric = Number.parseFloat(normalizeStyleValue(value));

    if (!Number.isFinite(numeric)) {
        return 100;
    }

    return Math.max(0, Math.min(100, Math.round(numeric * 100)));
}

export function StyleInputRow({
    label,
    value,
    onChange,
    onClear,
    placeholder,
    suffix,
    type = 'text',
    containerClassName,
    layout = 'horizontal',
}: StyleInputRowProps) {
    const isStacked = layout === 'stacked';
    const normalizedValue = normalizeStyleValue(value);

    return (
        <div
            className={cn(
                'relative rounded-lg border border-border/50 bg-muted/20 px-3 py-2.5',
                isStacked ? 'flex flex-col gap-2' : 'flex items-center justify-between gap-2.5',
            )}
        >
            {onClear && normalizedValue.trim() !== '' ? <ClearValueButton onClick={onClear} label={`Clear ${label}`} /> : null}
            <label className={cn('text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground', isStacked ? 'pr-6' : 'pr-6')}>
                {label}
            </label>
            <div className={cn(isStacked ? 'relative w-full pr-4' : 'relative w-32 shrink-0 pr-4', containerClassName)}>
                <input
                    type={type}
                    value={normalizedValue}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    className="h-7 w-full rounded-md border border-border/60 bg-background px-2.5 text-[12px] outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/15"
                />
                {suffix ? (
                    <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] text-muted-foreground">
                        {suffix}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

export function StyleCheckboxRow({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-2.5 rounded-lg border border-border/50 bg-muted/20 px-2.5 py-2">
            <label className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</label>
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="size-4 rounded border-border/70 text-primary focus:ring-primary/20"
            />
        </div>
    );
}

export function StyleSelectRow({
    label,
    value,
    onChange,
    onClear,
    options,
}: {
    label: string;
    value: string | undefined;
    onChange: (value: string) => void;
    onClear?: () => void;
    options: Array<{ value: string; label: string }>;
}) {
    const normalizedValue = normalizeStyleValue(value);

    return (
        <div className="relative flex items-center justify-between gap-2.5 rounded-lg border border-border/50 bg-muted/20 px-3 py-2.5">
            {onClear && normalizedValue.trim() !== '' ? <ClearValueButton onClick={onClear} label={`Clear ${label}`} /> : null}
            <label className="pr-6 text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</label>
            <select
                value={normalizedValue}
                onChange={(event) => onChange(event.target.value)}
                className="h-7 w-32 rounded-md border border-border/60 bg-background px-2.5 pr-7 text-[12px] outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/15"
            >
                {options.map((option) => (
                    <option key={option.value || 'default'} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

export function ColorStyleRow({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string | undefined;
    onChange: (value: string) => void;
}) {
    const normalizedValue = normalizeStyleValue(value);
    const swatchColor = normalizedValue.trim() === '' ? '#000000' : normalizedValue;

    return (
        <div className="relative rounded-lg border border-border/50 bg-muted/20 px-3 py-2.5">
            {normalizedValue.trim() !== '' ? <ClearValueButton onClick={() => onChange('')} label={`Clear ${label}`} /> : null}
            <div className="flex flex-col gap-2 pr-6">
                <div className="flex items-center gap-3">
                    <span className="min-w-0 flex-1 text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</span>
                    <label className="relative size-5 shrink-0 overflow-hidden rounded-full border border-border/70 shadow-sm">
                        <span className="absolute inset-0" style={{ backgroundColor: swatchColor }} />
                        <input
                            type="color"
                            value={swatchColor}
                            onChange={(event) => onChange(event.target.value)}
                            className="absolute inset-0 cursor-pointer opacity-0"
                            aria-label={`${label} color`}
                        />
                    </label>
                </div>
                <input
                    type="text"
                    value={normalizedValue}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="rgba(0, 0, 0, 1)"
                    className="h-7 w-full rounded-md border border-border/60 bg-background px-2.5 text-[12px] text-muted-foreground outline-none transition-colors placeholder:text-muted-foreground/60 focus:border-primary/50 focus:ring-1 focus:ring-primary/15"
                />
            </div>
        </div>
    );
}

export function SliderStyleRow({
    label,
    value,
    onChange,
    emptyLabel = 'auto',
    min,
    max,
    step = 1,
    displayValue,
    parseValue,
    toValue,
}: SliderStyleRowProps) {
    const normalizedValue = normalizeStyleValue(value);
    const resolvedValue = parseValue ? parseValue(normalizedValue) : parseLengthValue(normalizedValue);
    const hasExplicitValue = normalizedValue.trim() !== '';
    const display = hasExplicitValue
        ? (displayValue ? displayValue(resolvedValue, normalizedValue) : normalizedValue.trim())
        : emptyLabel;

    return (
        <div className="relative rounded-lg border border-border/50 bg-muted/20 px-3 py-2.5">
            {hasExplicitValue ? <ClearValueButton onClick={() => onChange('')} label={`Clear ${label}`} /> : null}
            <div className="mb-2 flex items-center justify-between gap-3 pr-6">
                <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</span>
                <span className="text-[12px] text-foreground/80">{display}</span>
            </div>
            <Slider
                min={min}
                max={max}
                step={step}
                value={[resolvedValue]}
                onValueChange={(values) => {
                    const [nextValue] = values;

                    if (typeof nextValue !== 'number') {
                        return;
                    }

                    onChange(toValue ? toValue(nextValue) : `${formatSliderNumber(nextValue, step < 1 ? 2 : 0)}px`);
                }}
                className="px-0.5"
            />
        </div>
    );
}

function ClearValueButton({ onClick, label }: { onClick: () => void; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className="absolute top-0 right-0 z-10 inline-flex size-[18px] translate-x-[34%] -translate-y-[34%] items-center justify-center rounded-full border border-border/70 bg-background text-muted-foreground shadow-sm transition-colors hover:text-foreground"
        >
            <XIcon className="size-2.5" />
        </button>
    );
}

export function LengthSliderRow({
    label,
    value,
    onChange,
    min = 0,
    max = 200,
}: {
    label: string;
    value: string | undefined;
    onChange: (value: string) => void;
    min?: number;
    max?: number;
}) {
    return (
        <SliderStyleRow
            label={label}
            value={value}
            onChange={onChange}
            emptyLabel="auto"
            min={min}
            max={max}
            displayValue={(nextValue) => `${formatSliderNumber(nextValue, 0)}px`}
            toValue={(nextValue) => `${formatSliderNumber(nextValue, 0)}px`}
        />
    );
}

export function OpacitySliderRow({
    value,
    onChange,
}: {
    value: string | undefined;
    onChange: (value: string) => void;
}) {
    const numericValue = parseOpacityValue(value);

    return (
        <SliderStyleRow
            label="Opacity"
            value={value}
            emptyLabel="auto"
            min={0}
            max={100}
            onChange={onChange}
            parseValue={parseOpacityValue}
            displayValue={() => `${numericValue}%`}
            toValue={(nextValue) => `${nextValue / 100}`}
        />
    );
}

export function SegmentedControlRow({
    label,
    value,
    displayLabel,
    onChange,
    onClear,
    options,
}: {
    label: string;
    value: string | undefined;
    displayLabel: string;
    onChange: (value: string) => void;
    onClear?: () => void;
    options: SegmentedControlOption[];
}) {
    const normalizedRawValue = normalizeStyleValue(value);
    const normalizedValue = normalizedRawValue.trim() === '' ? '__default__' : normalizedRawValue;

    return (
        <div className="relative rounded-lg border border-border/50 bg-muted/20 px-3 py-2.5">
            {onClear && normalizedRawValue.trim() !== '' ? <ClearValueButton onClick={onClear} label={`Clear ${label}`} /> : null}
            <div className="mb-2 flex items-center justify-between gap-3 pr-6">
                <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</span>
                <span className="text-[12px] text-foreground/80">{displayLabel}</span>
            </div>
            <ToggleGroup
                type="single"
                variant="outline"
                size="comfortable"
                spacing={0}
                value={normalizedValue}
                onValueChange={(nextValue) => {
                    if (nextValue === '') {
                        return;
                    }

                    onChange(nextValue === '__default__' ? '' : nextValue);
                }}
                className="w-full overflow-hidden rounded-lg border border-border/70 bg-background"
            >
                {options.map((option) => (
                    <ToggleGroupItem
                        key={option.ariaLabel}
                        value={option.value}
                        aria-label={option.ariaLabel}
                        className="h-7 flex-1 rounded-none border-border/70 px-0 text-foreground/75 data-[state=on]:bg-foreground data-[state=on]:text-background hover:bg-muted/60 hover:text-foreground"
                    >
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </div>
    );
}

export function ColorSummarySwatches({
    backgroundColor,
    textColor,
}: {
    backgroundColor: string;
    textColor: string;
}) {
    const swatches = [backgroundColor.trim(), textColor.trim()].filter((entry) => entry !== '');

    if (swatches.length === 0) {
        return <span className="ml-auto truncate text-[12px] text-muted-foreground">auto</span>;
    }

    return (
        <span className="ml-auto flex items-center gap-1.5 text-[12px] text-muted-foreground">
            {swatches.map((color, index) => (
                <span key={`${color}-${index}`} className="inline-flex items-center">
                    <span
                        className="size-2.5 rounded-full border border-border/70 shadow-sm"
                        style={{ backgroundColor: color }}
                        aria-hidden="true"
                    />
                </span>
            ))}
        </span>
    );
}

export function SummaryText({ value }: { value: string }) {
    return <span className="ml-auto max-w-[9.5rem] truncate text-right text-[12px] text-muted-foreground">{value}</span>;
}

export function InspectorSection({ value, title, summary, children }: InspectorSectionProps) {
    return (
        <AccordionItem value={value} className="overflow-visible border-b border-border/40 px-3 last:border-b-0">
            <AccordionTrigger className="py-2.5 text-sm hover:no-underline">
                <div className="flex min-w-0 flex-1 items-center gap-2 pr-4 text-left">
                    <span className="text-[13px] font-medium text-foreground">{title}</span>
                    {summary ? summary : null}
                </div>
            </AccordionTrigger>
            <AccordionContent className="overflow-visible pb-3">
                <div className="flex flex-col gap-3 pr-5">{children}</div>
            </AccordionContent>
        </AccordionItem>
    );
}