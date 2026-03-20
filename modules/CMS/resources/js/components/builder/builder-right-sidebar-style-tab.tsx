import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
} from 'lucide-react';
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

type StyleTabProps = {
    selectedElement: BuilderEditableElement;
    onUpdateElementField: (
        field: 'id' | 'className' | 'href' | 'textContent' | 'target' | 'rel' | 'buttonType' | 'disabled',
        value: string,
    ) => void;
    onUpdateElementStyle: (
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onUpdateElementInteractiveStyle: (
        stateKey: 'hoverStyles' | 'focusStyles',
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
};

export function StyleTab({
    selectedElement,
    onUpdateElementField,
    onUpdateElementStyle,
    onUpdateElementInteractiveStyle,
}: StyleTabProps) {
    const attributeSummary = <SummaryText value={selectedElement.id.trim() || selectedElement.className.trim() || 'Element metadata'} />;
    const dimensionsSummary = <SummaryText value={`${formatDimensionValue(selectedElement.styles.width)} x ${formatDimensionValue(selectedElement.styles.height)}`} />;
    const typographySummary = <SummaryText value={formatStyleValue(selectedElement.styles.fontSize, 'Text controls')} />;
    const colorsSummary = (
        <ColorSummarySwatches
            backgroundColor={selectedElement.styles.backgroundColor}
            textColor={selectedElement.styles.color}
        />
    );
    const contentSummaryValue = selectedElement.isLink
        ? selectedElement.href.trim() || formatStyleValue(selectedElement.textContent, 'Empty label')
        : selectedElement.isButton
            ? formatStyleValue(selectedElement.textContent, 'Empty label')
            : '';
    const contentSummary = contentSummaryValue === '' ? undefined : <SummaryText value={contentSummaryValue} />;
    const interactiveSummary = selectedElement.isLink || selectedElement.isButton
        ? <SummaryText value={selectedElement.isLink ? (selectedElement.target || 'Current tab') : (selectedElement.disabled ? 'Disabled' : (selectedElement.buttonType || 'button'))} />
        : undefined;
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
    const decorationSummary = <SummaryText value={formatLengthValue(selectedElement.styles.borderRadius)} />;

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
        {
            value: 'justify',
            ariaLabel: 'Justify text',
            label: <AlignJustifyIcon className="size-3.5" />,
        },
    ];

    return (
        <div className="flex flex-col">
            <Accordion
                type="multiple"
                defaultValue={['attributes', 'typography', 'colors']}
                className="w-full"
            >
                <InspectorSection value="attributes" title="Attributes" summary={attributeSummary}>
                    <StyleInputRow
                        label="ID"
                        value={selectedElement.id}
                        onChange={(value) => onUpdateElementField('id', value)}
                        placeholder="element-id"
                        containerClassName="w-36"
                    />
                    <StyleInputRow
                        label="Classes"
                        value={selectedElement.className}
                        onChange={(value) => onUpdateElementField('className', value)}
                        placeholder="class-name"
                        containerClassName="w-40"
                    />
                </InspectorSection>

                {selectedElement.isLink || selectedElement.isButton ? (
                    <InspectorSection value="content" title="Content" summary={contentSummary}>
                        <StyleInputRow
                            label="Label"
                            value={selectedElement.textContent}
                            onChange={(value) => onUpdateElementField('textContent', value)}
                            placeholder="Button label"
                            containerClassName="w-40"
                        />
                        {selectedElement.isLink ? (
                            <StyleInputRow
                                label="URL"
                                value={selectedElement.href}
                                onChange={(value) => onUpdateElementField('href', value)}
                                placeholder="https://example.com"
                                containerClassName="w-40"
                            />
                        ) : null}
                    </InspectorSection>
                ) : null}

                {selectedElement.isLink || selectedElement.isButton ? (
                    <InspectorSection value="interactive" title="Interactive" summary={interactiveSummary}>
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
                                    containerClassName="w-40"
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
                    </InspectorSection>
                ) : null}

                <InspectorSection value="dimensions" title="Dimensions" summary={dimensionsSummary}>
                    <StyleInputRow
                        label="Width"
                        value={selectedElement.styles.width}
                        onChange={(value) => onUpdateElementStyle('width', value)}
                        placeholder="100%"
                    />
                    <StyleInputRow
                        label="Height"
                        value={selectedElement.styles.height}
                        onChange={(value) => onUpdateElementStyle('height', value)}
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
                        min={8}
                        max={120}
                        displayValue={(value, rawValue) => rawValue.trim() || `${formatSliderNumber(value, 0)}px`}
                        toValue={(value) => `${formatSliderNumber(value, 0)}px`}
                    />
                    <StyleSelectRow
                        label="Weight"
                        value={selectedElement.styles.fontWeight}
                        onChange={(value) => onUpdateElementStyle('fontWeight', value)}
                        options={FONT_WEIGHT_OPTIONS}
                    />
                    <SliderStyleRow
                        label="Line height"
                        value={selectedElement.styles.lineHeight}
                        onChange={(value) => onUpdateElementStyle('lineHeight', value)}
                        min={0.8}
                        max={3}
                        step={0.05}
                        parseValue={(value) => parseNumericValue(value, 1.4)}
                        displayValue={(value, rawValue) => rawValue.trim() || formatSliderNumber(value, 2)}
                        toValue={(value) => formatSliderNumber(value, 2)}
                    />
                    <SliderStyleRow
                        label="Letter spacing"
                        value={selectedElement.styles.letterSpacing}
                        onChange={(value) => onUpdateElementStyle('letterSpacing', value)}
                        min={-2}
                        max={24}
                        step={0.5}
                        parseValue={(value) => parseNumericValue(value, 0)}
                        displayValue={(value, rawValue) => rawValue.trim() || `${formatSliderNumber(value, 1)}px`}
                        toValue={(value) => `${formatSliderNumber(value, 1)}px`}
                    />
                    <SegmentedControlRow
                        label="Text align"
                        value={selectedElement.styles.textAlign}
                        displayLabel={formatAlignmentLabel(selectedElement.styles.textAlign)}
                        onChange={(value) => onUpdateElementStyle('textAlign', value)}
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
                    <div className="grid grid-cols-2 gap-2">
                        <LengthSliderRow label="Top" value={selectedElement.styles.marginTop} onChange={(value) => onUpdateElementStyle('marginTop', value)} />
                        <LengthSliderRow label="Right" value={selectedElement.styles.marginRight} onChange={(value) => onUpdateElementStyle('marginRight', value)} />
                        <LengthSliderRow label="Bottom" value={selectedElement.styles.marginBottom} onChange={(value) => onUpdateElementStyle('marginBottom', value)} />
                        <LengthSliderRow label="Left" value={selectedElement.styles.marginLeft} onChange={(value) => onUpdateElementStyle('marginLeft', value)} />
                    </div>
                </InspectorSection>

                <InspectorSection value="padding" title="Padding" summary={paddingSummary}>
                    <div className="grid grid-cols-2 gap-2">
                        <LengthSliderRow label="Top" value={selectedElement.styles.paddingTop} onChange={(value) => onUpdateElementStyle('paddingTop', value)} />
                        <LengthSliderRow label="Right" value={selectedElement.styles.paddingRight} onChange={(value) => onUpdateElementStyle('paddingRight', value)} />
                        <LengthSliderRow label="Bottom" value={selectedElement.styles.paddingBottom} onChange={(value) => onUpdateElementStyle('paddingBottom', value)} />
                        <LengthSliderRow label="Left" value={selectedElement.styles.paddingLeft} onChange={(value) => onUpdateElementStyle('paddingLeft', value)} />
                    </div>
                </InspectorSection>

                <InspectorSection value="decoration" title="Decoration" summary={decorationSummary}>
                    <LengthSliderRow
                        label="Radius"
                        value={selectedElement.styles.borderRadius}
                        onChange={(value) => onUpdateElementStyle('borderRadius', value)}
                        max={96}
                    />
                </InspectorSection>
            </Accordion>
        </div>
    );
}