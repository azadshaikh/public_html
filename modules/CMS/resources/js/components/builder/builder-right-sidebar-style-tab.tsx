import { ImagePlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import {
    AlignCenterIcon,
    AlignLeftIcon,
    AlignRightIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { MediaPickerDialog } from '@/components/media/media-picker-dialog';
import type { MediaPickerItem } from '@/components/media/media-picker-utils';
import { Accordion } from '@/components/ui/accordion';
import type { BuilderEditableElement, BuilderElementStyleValues } from './builder-dom';
import {
    ColorStyleRow,
    ColorSummarySwatches,
    FONT_WEIGHT_OPTIONS,
    formatAlignmentLabel,
    formatDimensionValue,
    formatLengthValue,
    formatSliderNumber,
    formatSpacingSummary,
    formatStyleValue,
    InspectorSection,
    LengthSliderRow,
    OpacitySliderRow,
    parseNumericValue,
    SegmentedControlRow,
    type SegmentedControlOption,
    SliderStyleRow,
    StyleCheckboxRow,
    StyleInputRow,
    StyleSelectRow,
    SummaryText,
} from './builder-right-sidebar-controls';
import type { MediaPickerPageProps } from '../../types/cms';

type InspectorTabProps = {
    selectedElement: BuilderEditableElement;
    pickerAction: string;
    onClearAllStyles: () => void;
    onUpdateElementField: (
        field: 'id' | 'className' | 'href' | 'textContent' | 'target' | 'rel' | 'buttonType' | 'disabled' | 'src' | 'alt',
        value: string,
    ) => void;
    onUpdateElementStyle: (
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onUpdateElementStyles: (styles: Partial<BuilderElementStyleValues>) => void;
    onUpdateElementInteractiveStyle: (
        stateKey: 'hoverStyles' | 'focusStyles',
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
} & MediaPickerPageProps;

type ContentTabProps = Pick<InspectorTabProps,
    'selectedElement'
    | 'pickerAction'
    | 'pickerFilters'
    | 'pickerMedia'
    | 'pickerStatistics'
    | 'uploadSettings'
    | 'onUpdateElementField'
    | 'onUpdateElementStyle'
    | 'onUpdateElementStyles'
>;

export function ContentTab({
    selectedElement,
    pickerAction,
    pickerFilters,
    pickerMedia,
    pickerStatistics,
    uploadSettings,
    onUpdateElementField,
    onUpdateElementStyle,
    onUpdateElementStyles,
}: ContentTabProps) {
    const [imagePickerOpen, setImagePickerOpen] = useState(false);
    const imageAlignmentOptions: SegmentedControlOption[] = [
        {
            value: '__default__',
            ariaLabel: 'Default image alignment',
            label: <span className="text-sm leading-none">×</span>,
        },
        {
            value: 'left',
            ariaLabel: 'Align image left',
            label: <AlignLeftIcon className="size-3.5" />,
        },
        {
            value: 'center',
            ariaLabel: 'Align image center',
            label: <AlignCenterIcon className="size-3.5" />,
        },
        {
            value: 'right',
            ariaLabel: 'Align image right',
            label: <AlignRightIcon className="size-3.5" />,
        },
    ];
    const currentImageAlignment = (() => {
        if (!selectedElement.isImage || selectedElement.styles.display !== 'block') {
            return '';
        }

        if (selectedElement.styles.marginLeft === 'auto' && selectedElement.styles.marginRight === 'auto') {
            return 'center';
        }

        if (selectedElement.styles.marginRight === 'auto' && selectedElement.styles.marginLeft !== 'auto') {
            return 'left';
        }

        if (selectedElement.styles.marginLeft === 'auto' && selectedElement.styles.marginRight !== 'auto') {
            return 'right';
        }

        return '';
    })();
    const handleSelectImage = (items: MediaPickerItem[]): void => {
        const item = items[0];

        if (!item) {
            return;
        }

        const nextSource = item.media_url || item.original_url || item.thumbnail_url || '';

        onUpdateElementField('src', nextSource);

        if ((selectedElement.alt ?? '').trim() === '' && item.alt_text.trim() !== '') {
            onUpdateElementField('alt', item.alt_text);
        }

        setImagePickerOpen(false);
    };
    const handleUpdateImageAlignment = (value: string): void => {
        if (value === '') {
            onUpdateElementStyles({
                display: '',
                marginLeft: '',
                marginRight: '',
            });

            return;
        }

        if (value === 'left') {
            onUpdateElementStyles({
                display: 'block',
                marginLeft: '0',
                marginRight: 'auto',
            });

            return;
        }

        if (value === 'center') {
            onUpdateElementStyles({
                display: 'block',
                marginLeft: 'auto',
                marginRight: 'auto',
            });

            return;
        }

        onUpdateElementStyles({
            display: 'block',
            marginLeft: 'auto',
            marginRight: '0',
        });
    };

    return (
        <div className="flex flex-col gap-4 p-3">
            <ContentGroup
                title="Attributes"
                description="Basic element metadata used for targeting and styling hooks."
            >
                    <StyleInputRow
                        label="ID"
                        value={selectedElement.id}
                        onChange={(value) => onUpdateElementField('id', value)}
                        placeholder="element-id"
                        layout="stacked"
                    />
                    <StyleInputRow
                        label="Classes"
                        value={selectedElement.className}
                        onChange={(value) => onUpdateElementField('className', value)}
                        placeholder="class-name"
                        layout="stacked"
                    />
            </ContentGroup>

            {selectedElement.isImage ? (
                <ContentGroup
                    title="Image"
                    description="Source, accessibility, sizing, and alignment for the selected image."
                >
                        <div className="flex flex-col gap-2.5 rounded-lg border border-border/50 bg-muted/20 p-3">
                            <button
                                type="button"
                                onClick={() => setImagePickerOpen(true)}
                                className="group flex min-h-[152px] w-full items-center justify-center overflow-hidden rounded-xl border border-dashed border-border/70 bg-background transition hover:border-primary/40 hover:bg-muted/40"
                            >
                                {selectedElement.src.trim() !== '' ? (
                                    <img
                                        src={selectedElement.src}
                                        alt={selectedElement.alt || 'Selected builder image'}
                                        className="max-h-[220px] w-full object-contain"
                                    />
                                ) : (
                                    <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                        <ImagePlusIcon className="size-6 opacity-50" />
                                        <span className="text-xs font-medium">Choose image</span>
                                    </div>
                                )}
                            </button>
                            <div className="flex items-center gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={() => setImagePickerOpen(true)}>
                                    <ImagePlusIcon data-icon="inline-start" />
                                    {selectedElement.src.trim() === '' ? 'Choose' : 'Change'}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => onUpdateElementField('src', '')}
                                    disabled={selectedElement.src.trim() === ''}
                                >
                                    <Trash2Icon data-icon="inline-start" />
                                    Remove
                                </Button>
                            </div>
                        </div>
                        <StyleInputRow
                            label="Source"
                            value={selectedElement.src}
                            onChange={(value) => onUpdateElementField('src', value)}
                            placeholder="https://example.com/image.jpg"
                            layout="stacked"
                        />
                        <StyleInputRow
                            label="Alt text"
                            value={selectedElement.alt}
                            onChange={(value) => onUpdateElementField('alt', value)}
                            placeholder="Describe this image"
                            layout="stacked"
                        />
                        <StyleInputRow
                            label="Width"
                            value={selectedElement.styles.width}
                            onChange={(value) => onUpdateElementStyle('width', value)}
                            onClear={() => onUpdateElementStyle('width', '')}
                            placeholder="320px or 100%"
                            layout="stacked"
                        />
                        <StyleInputRow
                            label="Height"
                            value={selectedElement.styles.height}
                            onChange={(value) => onUpdateElementStyle('height', value)}
                            onClear={() => onUpdateElementStyle('height', '')}
                            placeholder="auto or 240px"
                            layout="stacked"
                        />
                        <SegmentedControlRow
                            label="Align"
                            value={currentImageAlignment}
                            displayLabel={currentImageAlignment === '' ? 'auto' : currentImageAlignment}
                            onChange={handleUpdateImageAlignment}
                            onClear={() => handleUpdateImageAlignment('')}
                            options={imageAlignmentOptions}
                        />
                        <MediaPickerDialog
                            open={imagePickerOpen}
                            onOpenChange={setImagePickerOpen}
                            onSelect={handleSelectImage}
                            selection="single"
                            title="Select image"
                            pickerMedia={pickerMedia}
                            pickerFilters={pickerFilters}
                            uploadSettings={uploadSettings}
                            pickerAction={pickerAction}
                            pickerStatistics={pickerStatistics}
                        />
                </ContentGroup>
            ) : null}

            {selectedElement.isLink || selectedElement.isButton ? (
                <ContentGroup
                    title={selectedElement.isLink ? 'Link Content' : 'Button Content'}
                    description={selectedElement.isLink ? 'Visible label and destination for this link.' : 'Visible label and submission intent for this button.'}
                >
                        <StyleInputRow
                            label="Label"
                            value={selectedElement.textContent}
                            onChange={(value) => onUpdateElementField('textContent', value)}
                            placeholder="Button label"
                            layout="stacked"
                        />
                        {selectedElement.isLink ? (
                            <StyleInputRow
                                label="URL"
                                value={selectedElement.href}
                                onChange={(value) => onUpdateElementField('href', value)}
                                placeholder="https://example.com"
                                layout="stacked"
                            />
                        ) : null}
                </ContentGroup>
            ) : null}

            {selectedElement.isLink || selectedElement.isButton ? (
                <ContentGroup
                    title="Interaction"
                    description={selectedElement.isLink ? 'How this link opens and which relationship attributes it uses.' : 'Control the button type and disabled state.'}
                >
                        {selectedElement.isLink ? (
                            <>
                                <StyleSelectRow
                                    label="Target"
                                    value={selectedElement.target}
                                    onChange={(value) => onUpdateElementField('target', value)}
                                    options={[
                                        { value: '', label: 'Current tab' },
                                        { value: '_blank', label: 'New tab' },
                                        { value: '_self', label: 'Self' },
                                        { value: '_parent', label: 'Parent' },
                                        { value: '_top', label: 'Top' },
                                    ]}
                                />
                                <StyleInputRow
                                    label="Rel"
                                    value={selectedElement.rel}
                                    onChange={(value) => onUpdateElementField('rel', value)}
                                    placeholder="noopener noreferrer"
                                    layout="stacked"
                                />
                            </>
                        ) : null}
                        {selectedElement.isButton ? (
                            <>
                                <StyleSelectRow
                                    label="Type"
                                    value={selectedElement.buttonType}
                                    onChange={(value) => onUpdateElementField('buttonType', value)}
                                    options={[
                                        { value: '', label: 'button' },
                                        { value: 'button', label: 'button' },
                                        { value: 'submit', label: 'submit' },
                                        { value: 'reset', label: 'reset' },
                                    ]}
                                />
                                <StyleCheckboxRow
                                    label="Disabled"
                                    checked={selectedElement.disabled}
                                    onChange={(checked) => onUpdateElementField('disabled', checked ? 'true' : '')}
                                />
                            </>
                        ) : null}
                </ContentGroup>
            ) : null}
        </div>
    );
}

function ContentGroup({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3 rounded-xl border border-border/50 bg-muted/10 p-3">
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-semibold text-foreground">{title}</h3>
                {description ? <p className="text-xs leading-5 text-muted-foreground">{description}</p> : null}
            </div>
            <div className="flex flex-col gap-3">{children}</div>
        </section>
    );
}

export function StyleTab({
    selectedElement,
    onClearAllStyles,
    onUpdateElementStyle,
    onUpdateElementStyles,
    onUpdateElementInteractiveStyle,
}: InspectorTabProps) {
    const defaultExpandedSections = [
        'dimensions',
        'typography',
        ...(selectedElement.isLink || selectedElement.isButton ? ['states'] : []),
        'colors',
        'margin',
        'padding',
        'border',
    ];
    const [expandedSections, setExpandedSections] = useState<string[]>(defaultExpandedSections);
    const dimensionsSummary = <SummaryText value={`${formatDimensionValue(selectedElement.styles.width)} x ${formatDimensionValue(selectedElement.styles.height)}`} />;
    const typographySummary = <SummaryText value={formatStyleValue(selectedElement.styles.fontSize, 'Text controls')} />;
    const colorsSummary = (
        <ColorSummarySwatches
            backgroundColor={selectedElement.styles.backgroundColor}
            textColor={selectedElement.styles.color}
        />
    );
    const statesSummary = selectedElement.isLink || selectedElement.isButton
        ? <SummaryText value="Hover + Focus" />
        : undefined;
    const marginSummary = <SummaryText value={formatSpacingSummary(
        selectedElement.styles.marginTop,
        selectedElement.styles.marginRight,
        selectedElement.styles.marginBottom,
        selectedElement.styles.marginLeft,
    )} />;
    const paddingSummary = <SummaryText value={formatSpacingSummary(
        selectedElement.styles.paddingTop,
        selectedElement.styles.paddingRight,
        selectedElement.styles.paddingBottom,
        selectedElement.styles.paddingLeft,
    )} />;
    const borderSummary = <SummaryText value={[
        formatStyleValue(selectedElement.styles.borderStyle, ''),
        formatLengthValue(selectedElement.styles.borderWidth, ''),
        formatStyleValue(selectedElement.styles.borderColor, ''),
    ].filter((value) => value !== '').join(' ') || formatStyleValue(selectedElement.styles.borderRadius, 'auto')} />;
    const hasCustomStyles = Object.values(selectedElement.styles).some((value) => (value ?? '').trim() !== '')
        || Object.values(selectedElement.hoverStyles).some((value) => (value ?? '').trim() !== '')
        || Object.values(selectedElement.focusStyles).some((value) => (value ?? '').trim() !== '');
    const handleConfirmClearAll = (): void => {
        if (!hasCustomStyles) {
            return;
        }

        if (!window.confirm('Clear all custom styles for the selected element?')) {
            return;
        }

        onClearAllStyles();
    };
    const handleUpdateBorderCorner = (
        field: 'borderTopLeftRadius' | 'borderTopRightRadius' | 'borderBottomRightRadius' | 'borderBottomLeftRadius',
        value: string,
    ): void => {
        const sharedRadius = selectedElement.styles.borderRadius ?? '';

        onUpdateElementStyles({
            borderRadius: '',
            borderTopLeftRadius: selectedElement.styles.borderTopLeftRadius || sharedRadius,
            borderTopRightRadius: selectedElement.styles.borderTopRightRadius || sharedRadius,
            borderBottomRightRadius: selectedElement.styles.borderBottomRightRadius || sharedRadius,
            borderBottomLeftRadius: selectedElement.styles.borderBottomLeftRadius || sharedRadius,
            [field]: value,
        });
    };
    const handleUpdateBorderStyles = (styles: Partial<BuilderElementStyleValues>): void => {
        const nextStyles = { ...styles };
        const resolvedBorderStyle = styles.borderStyle ?? selectedElement.styles.borderStyle ?? '';
        const touchesVisibleBorder = [styles.borderWidth, styles.borderColor].some((value) => typeof value === 'string' && value.trim() !== '');

        if (touchesVisibleBorder && (resolvedBorderStyle.trim() === '' || resolvedBorderStyle === 'none')) {
            nextStyles.borderStyle = 'solid';
        }

        onUpdateElementStyles(nextStyles);
    };
    const alignmentOptions: SegmentedControlOption[] = [
        {
            value: '__default__',
            ariaLabel: 'Default alignment',
            label: <span className="text-sm leading-none">×</span>,
        },
        {
            value: 'left',
            ariaLabel: 'Align left',
            label: <AlignLeftIcon className="size-3.5" />,
        },
        {
            value: 'center',
            ariaLabel: 'Align center',
            label: <AlignCenterIcon className="size-3.5" />,
        },
        {
            value: 'right',
            ariaLabel: 'Align right',
            label: <AlignRightIcon className="size-3.5" />,
        },
    ];
    alignmentOptions.push({
        value: 'justify',
        ariaLabel: 'Justify text',
        label: <span className="text-[11px] font-semibold leading-none">J</span>,
    });
    const borderStyleOptions = [
        { value: '', label: 'auto' },
        { value: 'solid', label: 'Solid' },
        { value: 'dashed', label: 'Dashed' },
        { value: 'dotted', label: 'Dotted' },
        { value: 'double', label: 'Double' },
        { value: 'none', label: 'None' },
    ];
    const resolvedBorderTopLeftRadius = selectedElement.styles.borderTopLeftRadius || selectedElement.styles.borderRadius;
    const resolvedBorderTopRightRadius = selectedElement.styles.borderTopRightRadius || selectedElement.styles.borderRadius;
    const resolvedBorderBottomRightRadius = selectedElement.styles.borderBottomRightRadius || selectedElement.styles.borderRadius;
    const resolvedBorderBottomLeftRadius = selectedElement.styles.borderBottomLeftRadius || selectedElement.styles.borderRadius;

    return (
        <div className="flex flex-col">
            <div className="sticky top-0 z-10 flex items-center justify-end gap-2 border-b border-border/40 bg-background px-3 py-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setExpandedSections([])}
                    disabled={expandedSections.length === 0}
                    className="h-7 px-2.5 text-[11px]"
                >
                    Collapse all
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={handleConfirmClearAll}
                    disabled={!hasCustomStyles}
                    className="h-7 px-2.5 text-[11px]"
                >
                    Clear all
                </Button>
            </div>
            <Accordion
                type="multiple"
                value={expandedSections}
                onValueChange={setExpandedSections}
                className="w-full"
            >
                <InspectorSection value="dimensions" title="Dimensions" summary={dimensionsSummary}>
                    <StyleInputRow
                        label="Width"
                        value={selectedElement.styles.width}
                        onChange={(value) => onUpdateElementStyle('width', value)}
                        onClear={() => onUpdateElementStyle('width', '')}
                        placeholder="100%"
                    />
                    <StyleInputRow
                        label="Height"
                        value={selectedElement.styles.height}
                        onChange={(value) => onUpdateElementStyle('height', value)}
                        onClear={() => onUpdateElementStyle('height', '')}
                        placeholder="auto"
                    />
                    <OpacitySliderRow
                        value={selectedElement.styles.opacity}
                        onChange={(value) => onUpdateElementStyle('opacity', value)}
                    />
                </InspectorSection>

                <InspectorSection value="typography" title="Typography" summary={typographySummary}>
                    <SliderStyleRow
                        label="Font size"
                        value={selectedElement.styles.fontSize}
                        onChange={(value) => onUpdateElementStyle('fontSize', value)}
                        emptyLabel="auto"
                        min={8}
                        max={120}
                        displayValue={(value) => `${formatSliderNumber(value, 0)}px`}
                        toValue={(value) => `${formatSliderNumber(value, 0)}px`}
                    />
                    <StyleSelectRow
                        label="Weight"
                        value={selectedElement.styles.fontWeight}
                        onChange={(value) => onUpdateElementStyle('fontWeight', value)}
                        onClear={() => onUpdateElementStyle('fontWeight', '')}
                        options={FONT_WEIGHT_OPTIONS}
                    />
                    <SliderStyleRow
                        label="Line height"
                        value={selectedElement.styles.lineHeight}
                        onChange={(value) => onUpdateElementStyle('lineHeight', value)}
                        emptyLabel="auto"
                        min={0.8}
                        max={3}
                        step={0.05}
                        parseValue={(value) => parseNumericValue(value, 1.4)}
                        displayValue={(value) => formatSliderNumber(value, 2)}
                        toValue={(value) => formatSliderNumber(value, 2)}
                    />
                    <SliderStyleRow
                        label="Letter spacing"
                        value={selectedElement.styles.letterSpacing}
                        onChange={(value) => onUpdateElementStyle('letterSpacing', value)}
                        emptyLabel="auto"
                        min={-2}
                        max={24}
                        step={0.5}
                        parseValue={(value) => parseNumericValue(value, 0)}
                        displayValue={(value) => `${formatSliderNumber(value, 1)}px`}
                        toValue={(value) => `${formatSliderNumber(value, 1)}px`}
                    />
                    <SegmentedControlRow
                        label="Text align"
                        value={selectedElement.styles.textAlign}
                        displayLabel={formatAlignmentLabel(selectedElement.styles.textAlign)}
                        onChange={(value) => onUpdateElementStyle('textAlign', value)}
                        onClear={() => onUpdateElementStyle('textAlign', '')}
                        options={alignmentOptions}
                    />
                </InspectorSection>

                {selectedElement.isLink || selectedElement.isButton ? (
                    <InspectorSection value="states" title="States" summary={statesSummary}>
                        <ColorStyleRow
                            label="Hover bg"
                            value={selectedElement.hoverStyles.backgroundColor ?? ''}
                            onChange={(value) => onUpdateElementInteractiveStyle('hoverStyles', 'backgroundColor', value)}
                        />
                        <ColorStyleRow
                            label="Hover text"
                            value={selectedElement.hoverStyles.color ?? ''}
                            onChange={(value) => onUpdateElementInteractiveStyle('hoverStyles', 'color', value)}
                        />
                        <ColorStyleRow
                            label="Focus bg"
                            value={selectedElement.focusStyles.backgroundColor ?? ''}
                            onChange={(value) => onUpdateElementInteractiveStyle('focusStyles', 'backgroundColor', value)}
                        />
                        <ColorStyleRow
                            label="Focus text"
                            value={selectedElement.focusStyles.color ?? ''}
                            onChange={(value) => onUpdateElementInteractiveStyle('focusStyles', 'color', value)}
                        />
                        <StyleInputRow
                            label="Focus ring"
                            value={selectedElement.focusStyles.boxShadow ?? ''}
                            onChange={(value) => onUpdateElementInteractiveStyle('focusStyles', 'boxShadow', value)}
                            onClear={() => onUpdateElementInteractiveStyle('focusStyles', 'boxShadow', '')}
                            placeholder="0 0 0 3px rgba(0,0,0,.15)"
                            containerClassName="w-40"
                        />
                    </InspectorSection>
                ) : null}

                <InspectorSection value="colors" title="Colors" summary={colorsSummary}>
                    <ColorStyleRow
                        label="Background"
                        value={selectedElement.styles.backgroundColor}
                        onChange={(value) => onUpdateElementStyle('backgroundColor', value)}
                    />
                    <ColorStyleRow
                        label="Text"
                        value={selectedElement.styles.color}
                        onChange={(value) => onUpdateElementStyle('color', value)}
                    />
                </InspectorSection>

                <InspectorSection value="margin" title="Margin" summary={marginSummary}>
                    <div className="grid grid-cols-2 gap-3">
                        <LengthSliderRow label="Top" value={selectedElement.styles.marginTop} onChange={(value) => onUpdateElementStyle('marginTop', value)} />
                        <LengthSliderRow label="Right" value={selectedElement.styles.marginRight} onChange={(value) => onUpdateElementStyle('marginRight', value)} />
                        <LengthSliderRow label="Bottom" value={selectedElement.styles.marginBottom} onChange={(value) => onUpdateElementStyle('marginBottom', value)} />
                        <LengthSliderRow label="Left" value={selectedElement.styles.marginLeft} onChange={(value) => onUpdateElementStyle('marginLeft', value)} />
                    </div>
                </InspectorSection>

                <InspectorSection value="padding" title="Padding" summary={paddingSummary}>
                    <div className="grid grid-cols-2 gap-3">
                        <LengthSliderRow label="Top" value={selectedElement.styles.paddingTop} onChange={(value) => onUpdateElementStyle('paddingTop', value)} />
                        <LengthSliderRow label="Right" value={selectedElement.styles.paddingRight} onChange={(value) => onUpdateElementStyle('paddingRight', value)} />
                        <LengthSliderRow label="Bottom" value={selectedElement.styles.paddingBottom} onChange={(value) => onUpdateElementStyle('paddingBottom', value)} />
                        <LengthSliderRow label="Left" value={selectedElement.styles.paddingLeft} onChange={(value) => onUpdateElementStyle('paddingLeft', value)} />
                    </div>
                </InspectorSection>

                <InspectorSection value="border" title="Border" summary={borderSummary}>
                    <StyleSelectRow
                        label="Border Style"
                        value={selectedElement.styles.borderStyle}
                        onChange={(value) => handleUpdateBorderStyles({ borderStyle: value })}
                        onClear={() => onUpdateElementStyle('borderStyle', '')}
                        options={borderStyleOptions}
                    />
                    <LengthSliderRow
                        label="Border Width"
                        value={selectedElement.styles.borderWidth}
                        onChange={(value) => handleUpdateBorderStyles({ borderWidth: value })}
                        min={0}
                        max={24}
                    />
                    <ColorStyleRow
                        label="Border Color"
                        value={selectedElement.styles.borderColor}
                        onChange={(value) => handleUpdateBorderStyles({ borderColor: value })}
                    />
                    <LengthSliderRow
                        label="Top Left Radius"
                        value={resolvedBorderTopLeftRadius}
                        onChange={(value) => handleUpdateBorderCorner('borderTopLeftRadius', value)}
                        max={96}
                    />
                    <LengthSliderRow
                        label="Top Right Radius"
                        value={resolvedBorderTopRightRadius}
                        onChange={(value) => handleUpdateBorderCorner('borderTopRightRadius', value)}
                        max={96}
                    />
                    <LengthSliderRow
                        label="Bottom Right Radius"
                        value={resolvedBorderBottomRightRadius}
                        onChange={(value) => handleUpdateBorderCorner('borderBottomRightRadius', value)}
                        max={96}
                    />
                    <LengthSliderRow
                        label="Bottom Left Radius"
                        value={resolvedBorderBottomLeftRadius}
                        onChange={(value) => handleUpdateBorderCorner('borderBottomLeftRadius', value)}
                        max={96}
                    />
                </InspectorSection>
            </Accordion>
        </div>
    );
}