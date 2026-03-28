<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class ThemeEditorControllerPatternConsistencyTest extends TestCase
{
    public function test_theme_editor_controller_uses_refactored_concerns(): void
    {
        $controllerContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/ThemeEditorController.php');

        $this->assertStringContainsString('use InteractsWithThemeEditorFileTree;', $controllerContents);
        $this->assertStringContainsString('use InteractsWithThemeEditorGit;', $controllerContents);
        $this->assertStringNotContainsString('public function gitHistory(', $controllerContents);
        $this->assertStringNotContainsString('public function gitRestoreCommit(', $controllerContents);
        $this->assertStringNotContainsString('private function getFileTree(', $controllerContents);
        $this->assertStringNotContainsString('private function mapThemeSummary(', $controllerContents);
    }

    public function test_theme_editor_concerns_hold_git_and_file_tree_behaviour(): void
    {
        $gitContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/Concerns/InteractsWithThemeEditorGit.php');
        $fileTreeContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/Concerns/InteractsWithThemeEditorFileTree.php');

        $this->assertStringContainsString('public function gitHistory(', $gitContents);
        $this->assertStringContainsString('public function gitStatus(', $gitContents);
        $this->assertStringContainsString('public function gitCommit(', $gitContents);
        $this->assertStringContainsString('public function gitRestore(', $gitContents);
        $this->assertStringContainsString('public function gitRestoreCommit(', $gitContents);

        $this->assertStringContainsString('private function getFileTree(', $fileTreeContents);
        $this->assertStringContainsString('private function mergeParentFiles(', $fileTreeContents);
        $this->assertStringContainsString('private function scanDirectory(', $fileTreeContents);
        $this->assertStringContainsString('private function validateFilePath(', $fileTreeContents);
        $this->assertStringContainsString('private function mapThemeSummary(', $fileTreeContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
