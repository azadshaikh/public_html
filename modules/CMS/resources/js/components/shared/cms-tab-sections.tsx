import type { ReactNode } from 'react';
import {
    PanelTabs,
    PanelTabsContent,
    PanelTabsList,
    PanelTabsTrigger,
} from '@/components/ui/panel-tabs';

type CmsTabSection = {
    value: string;
    label: string;
    content: ReactNode;
    contentClassName?: string;
};

type CmsTabSectionsProps = {
    defaultValue: string;
    tabs: CmsTabSection[];
};

export function CmsTabSections({
    defaultValue,
    tabs,
}: CmsTabSectionsProps) {
    return (
        <PanelTabs defaultValue={defaultValue}>
            <PanelTabsList>
                {tabs.map((tab) => (
                    <PanelTabsTrigger key={tab.value} value={tab.value}>
                        {tab.label}
                    </PanelTabsTrigger>
                ))}
            </PanelTabsList>

            {tabs.map((tab) => (
                <PanelTabsContent
                    key={tab.value}
                    value={tab.value}
                    className={tab.contentClassName}
                >
                    {tab.content}
                </PanelTabsContent>
            ))}
        </PanelTabs>
    );
}