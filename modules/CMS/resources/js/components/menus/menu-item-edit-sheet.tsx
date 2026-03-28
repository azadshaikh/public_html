import { SaveIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { DraftMenuItem } from './menu-editor-types';

type ItemEditSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: DraftMenuItem | null;
    itemTypes: Record<string, string>;
    itemTargets: Record<string, string>;
    onSave: (updated: DraftMenuItem) => void;
};

export function MenuItemEditSheet({
    open,
    onOpenChange,
    item,
    itemTypes,
    itemTargets,
    onSave,
}: ItemEditSheetProps) {
    const [draft, setDraft] = useState<DraftMenuItem | null>(() =>
        item ? { ...item } : null,
    );

    if (!draft) {
        return null;
    }

    const isInternalLinkedItem = ['page', 'category', 'tag'].includes(
        draft.type,
    );

    const setField = <K extends keyof DraftMenuItem>(
        key: K,
        value: DraftMenuItem[K],
    ) => {
        setDraft((previous) =>
            previous ? { ...previous, [key]: value } : previous,
        );
    };

    const handleSave = (event: FormEvent) => {
        event.preventDefault();
        if (!draft.title.trim()) {
            return;
        }

        onSave(draft);
        onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="flex w-full flex-col sm:max-w-md"
            >
                <SheetHeader className="px-6 pt-6 pb-4">
                    <SheetTitle>Edit Menu Item</SheetTitle>
                    <SheetDescription>
                        Update the properties of this navigation item.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-6 py-5">
                    <FieldGroup>
                        <form noValidate onSubmit={handleSave}>
                            <Accordion
                                type="multiple"
                                defaultValue={['basic', 'appearance', 'behavior']}
                            >
                                <AccordionItem value="basic">
                                    <AccordionTrigger>Basic</AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-title">
                                                Label{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <Input
                                                id="item-title"
                                                value={draft.title}
                                                onChange={(event) =>
                                                    setField(
                                                        'title',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Navigation label"
                                            />
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-url">
                                                URL
                                            </FieldLabel>
                                            <Input
                                                id="item-url"
                                                value={draft.url}
                                                onChange={(event) =>
                                                    setField(
                                                        'url',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="https://example.com or /path"
                                                readOnly={isInternalLinkedItem}
                                                disabled={isInternalLinkedItem}
                                            />
                                            {isInternalLinkedItem ? (
                                                <FieldDescription>
                                                    This URL is managed by the
                                                    linked content and updates
                                                    automatically when its slug
                                                    changes.
                                                </FieldDescription>
                                            ) : null}
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-type">
                                                Type
                                            </FieldLabel>
                                            <NativeSelect
                                                id="item-type"
                                                value={draft.type}
                                                onChange={(event) =>
                                                    setField(
                                                        'type',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {Object.entries(itemTypes).map(
                                                    ([value, label]) => (
                                                        <NativeSelectOption
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </NativeSelectOption>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>

                                        <Field orientation="horizontal">
                                            <Switch
                                                checked={draft.is_active}
                                                onCheckedChange={(checked) =>
                                                    setField('is_active', checked)
                                                }
                                            />
                                            <div className="flex flex-col gap-1">
                                                <FieldLabel>
                                                    Active
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Inactive items are hidden on
                                                    the front end.
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>

                                <AccordionItem value="appearance">
                                    <AccordionTrigger>
                                        Appearance
                                    </AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-icon">
                                                Icon Class
                                            </FieldLabel>
                                            <Input
                                                id="item-icon"
                                                value={draft.icon}
                                                onChange={(event) =>
                                                    setField(
                                                        'icon',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="e.g. fa-home or bi-house"
                                            />
                                            <FieldDescription>
                                                CSS class(es) for an icon
                                                library.
                                            </FieldDescription>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-css">
                                                CSS Classes
                                            </FieldLabel>
                                            <Input
                                                id="item-css"
                                                value={draft.css_classes}
                                                onChange={(event) =>
                                                    setField(
                                                        'css_classes',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Extra classes for the link element"
                                            />
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>

                                <AccordionItem value="behavior">
                                    <AccordionTrigger>
                                        Link Behavior
                                    </AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-target">
                                                Open In
                                            </FieldLabel>
                                            <NativeSelect
                                                id="item-target"
                                                value={draft.target}
                                                onChange={(event) =>
                                                    setField(
                                                        'target',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {Object.entries(itemTargets).map(
                                                    ([value, label]) => (
                                                        <NativeSelectOption
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </NativeSelectOption>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-link-title">
                                                Title Attribute
                                            </FieldLabel>
                                            <Input
                                                id="item-link-title"
                                                value={draft.link_title}
                                                onChange={(event) =>
                                                    setField(
                                                        'link_title',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Tooltip / title="
                                            />
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-link-rel">
                                                Rel Attribute
                                            </FieldLabel>
                                            <Input
                                                id="item-link-rel"
                                                value={draft.link_rel}
                                                onChange={(event) =>
                                                    setField(
                                                        'link_rel',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="e.g. noopener nofollow"
                                            />
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>

                                <AccordionItem value="advanced">
                                    <AccordionTrigger>Advanced</AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-description">
                                                Description
                                            </FieldLabel>
                                            <Textarea
                                                id="item-description"
                                                value={draft.description}
                                                onChange={(event) =>
                                                    setField(
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                rows={3}
                                                placeholder="Optional description shown in some themes."
                                            />
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>
                        </form>
                    </FieldGroup>
                </div>

                <SheetFooter className="px-6 pt-4 pb-6">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={!draft.title.trim()}
                        onClick={() => {
                            if (!draft.title.trim()) {
                                return;
                            }

                            onSave(draft);
                            onOpenChange(false);
                        }}
                    >
                        <SaveIcon data-icon="inline-start" />
                        Apply Changes
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
