import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { PageAst } from '../../../components/builder/core/ast-types';
import { buildAstFromPageContent, type FooterEditorDrafts, type FooterEditorTab } from './edit-support';

type FooterEditorActions = {
    clearSelection: () => void;
    setAst: (ast: PageAst) => void;
    setCss: (css: string) => void;
    setJs: (js: string) => void;
};

type UseBuilderFooterEditorOptions = {
    actions: FooterEditorActions;
    currentCss: string;
    currentJs: string;
    footerEditorSources: FooterEditorDrafts;
};

type UseBuilderFooterEditorResult = {
    footerEditorDrafts: FooterEditorDrafts;
    footerEditorFullscreen: boolean;
    footerEditorIsDirty: boolean;
    footerEditorLanguage: 'html' | 'css' | 'js';
    footerEditorOpen: boolean;
    footerEditorTab: FooterEditorTab;
    footerEditorTitle: string;
    footerEditorValue: string;
    handleApplyFooterEditor: () => void;
    handleCloseFooterEditor: () => void;
    handleFooterEditorValueChange: (value: string) => void;
    handleOpenFooterEditor: (tab: FooterEditorTab) => void;
    handleToggleFooterEditorFullscreen: () => void;
};

export function useBuilderFooterEditor({
    actions,
    currentCss,
    currentJs,
    footerEditorSources,
}: UseBuilderFooterEditorOptions): UseBuilderFooterEditorResult {
    const [footerEditorTab, setFooterEditorTab] = useState<FooterEditorTab>('html');
    const [footerEditorOpen, setFooterEditorOpen] = useState(false);
    const [footerEditorFullscreen, setFooterEditorFullscreen] = useState(false);
    const [footerEditorDrafts, setFooterEditorDrafts] = useState<FooterEditorDrafts>({
        html: '',
        css: '',
        js: '',
    });
    const [footerEditorHasManualChanges, setFooterEditorHasManualChanges] = useState<Record<FooterEditorTab, boolean>>({
        html: false,
        css: false,
        js: false,
    });
    const previousFooterEditorSourcesRef = useRef<FooterEditorDrafts | null>(null);

    const handleOpenFooterEditor = useCallback((tab: FooterEditorTab): void => {
        setFooterEditorDrafts((current) => {
            if (footerEditorHasManualChanges[tab]) {
                return current;
            }

            return {
                ...current,
                [tab]: footerEditorSources[tab],
            };
        });
        setFooterEditorTab(tab);
        setFooterEditorOpen(true);
        setFooterEditorFullscreen(false);
    }, [footerEditorHasManualChanges, footerEditorSources]);

    const handleCloseFooterEditor = useCallback((): void => {
        setFooterEditorOpen(false);
        setFooterEditorFullscreen(false);
    }, []);

    const handleToggleFooterEditorFullscreen = useCallback((): void => {
        setFooterEditorFullscreen((value) => !value);
    }, []);

    const handleFooterEditorValueChange = useCallback((value: string): void => {
        setFooterEditorDrafts((current) => ({
            ...current,
            [footerEditorTab]: value,
        }));
        setFooterEditorHasManualChanges((current) => ({
            ...current,
            [footerEditorTab]: value !== footerEditorSources[footerEditorTab],
        }));
    }, [footerEditorSources, footerEditorTab]);

    const handleApplyFooterEditor = useCallback((): void => {
        const currentDraft = footerEditorDrafts[footerEditorTab];

        if (footerEditorTab === 'css') {
            actions.setCss(currentDraft);
            setFooterEditorHasManualChanges((current) => ({
                ...current,
                css: false,
            }));

            return;
        }

        if (footerEditorTab === 'js') {
            actions.setJs(currentDraft);
            setFooterEditorHasManualChanges((current) => ({
                ...current,
                js: false,
            }));

            return;
        }

        const nextAst = buildAstFromPageContent(currentDraft, currentCss, currentJs);

        actions.setAst(nextAst);
        actions.clearSelection();
        setFooterEditorHasManualChanges((current) => ({
            ...current,
            html: false,
        }));
    }, [actions, currentCss, currentJs, footerEditorDrafts, footerEditorTab]);

    const footerEditorValue = footerEditorDrafts[footerEditorTab];

    const footerEditorIsDirty = useMemo(() => {
        return footerEditorValue !== footerEditorSources[footerEditorTab];
    }, [footerEditorSources, footerEditorTab, footerEditorValue]);

    const footerEditorLanguage = footerEditorTab === 'html' ? 'html' : footerEditorTab;

    const footerEditorTitle = footerEditorTab === 'html'
        ? 'HTML'
        : footerEditorTab === 'css'
            ? 'CSS'
            : 'JS';

    useEffect(() => {
        setFooterEditorDrafts((current) => {
            const previousSources = previousFooterEditorSourcesRef.current;
            const next = { ...current };

            for (const tab of ['html', 'css', 'js'] as FooterEditorTab[]) {
                if (
                    !footerEditorHasManualChanges[tab]
                    || previousSources === null
                    || current[tab] === previousSources[tab]
                    || current[tab] === footerEditorSources[tab]
                ) {
                    next[tab] = footerEditorSources[tab];
                }
            }

            return next;
        });

        previousFooterEditorSourcesRef.current = footerEditorSources;
    }, [footerEditorHasManualChanges, footerEditorSources]);

    return {
        footerEditorDrafts,
        footerEditorFullscreen,
        footerEditorIsDirty,
        footerEditorLanguage,
        footerEditorOpen,
        footerEditorTab,
        footerEditorTitle,
        footerEditorValue,
        handleApplyFooterEditor,
        handleCloseFooterEditor,
        handleFooterEditorValueChange,
        handleOpenFooterEditor,
        handleToggleFooterEditorFullscreen,
    };
}
