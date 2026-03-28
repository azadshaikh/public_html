<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class MenuEditorPagePatternConsistencyTest extends TestCase
{
    public function test_menu_editor_page_uses_extracted_components_and_utilities(): void
    {
        $pageContents = $this->readRequiredFile('modules/CMS/resources/js/pages/cms/menus/edit.tsx');

        $this->assertStringContainsString("import { MenuItemEditSheet } from '../../../components/menus/menu-item-edit-sheet';", $pageContents);
        $this->assertStringContainsString("import { MenuItemLibraryPanel } from '../../../components/menus/menu-item-library-panel';", $pageContents);
        $this->assertStringContainsString("import { MenuItemRow } from '../../../components/menus/menu-item-row';", $pageContents);
        $this->assertStringContainsString("from '../../../components/menus/menu-editor-utils';", $pageContents);
        $this->assertStringContainsString("from '../../../components/menus/menu-editor-types';", $pageContents);
        $this->assertStringNotContainsString('function MenuItemLibraryPanel(', $pageContents);
        $this->assertStringNotContainsString('function MenuItemRow(', $pageContents);
        $this->assertStringNotContainsString('function MenuItemEditSheet(', $pageContents);
    }

    public function test_menu_editor_support_files_export_expected_building_blocks(): void
    {
        $rowContents = $this->readRequiredFile('modules/CMS/resources/js/components/menus/menu-item-row.tsx');
        $libraryPanelContents = $this->readRequiredFile('modules/CMS/resources/js/components/menus/menu-item-library-panel.tsx');
        $editSheetContents = $this->readRequiredFile('modules/CMS/resources/js/components/menus/menu-item-edit-sheet.tsx');
        $utilityContents = $this->readRequiredFile('modules/CMS/resources/js/components/menus/menu-editor-utils.ts');

        $this->assertStringContainsString('export function MenuItemRow(', $rowContents);
        $this->assertStringContainsString('export function MenuItemLibraryPanel(', $libraryPanelContents);
        $this->assertStringContainsString('export function MenuItemEditSheet(', $editSheetContents);
        $this->assertStringContainsString('export function buildRenderOrder(', $utilityContents);
        $this->assertStringContainsString('export function applyDrop(', $utilityContents);
        $this->assertStringContainsString('export function indentItem(', $utilityContents);
        $this->assertStringContainsString('export function outdentItem(', $utilityContents);
        $this->assertStringContainsString('No {value} found.', $libraryPanelContents);
        $this->assertStringNotContainsString('if (items.length === 0) {', $libraryPanelContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
