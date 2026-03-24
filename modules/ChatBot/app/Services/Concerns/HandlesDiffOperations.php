<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait HandlesDiffOperations
{
    public function edit(string $path, string $oldString, string $newString, bool $replaceAll = false): string
    {
        [$filePath, $updated] = $this->applyEditsToPath($path, [[
            'oldString' => $oldString,
            'newString' => $newString,
            'replaceAll' => $replaceAll,
        ]]);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $updated);

        return 'Edit applied successfully.'.$this->diagnosticsSummary([$filePath]);
    }

    /**
     * @param  array<int, array{oldString: mixed, newString: mixed, replaceAll?: mixed}>  $edits
     */
    public function multiedit(string $path, array $edits): string
    {
        [$filePath, $updated] = $this->applyEditsToPath($path, $edits);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $updated);

        return 'Edit applied successfully.'.$this->diagnosticsSummary([$filePath]);
    }

    /**
     * @param  array<int, array{oldString: mixed, newString: mixed, replaceAll?: mixed}>  $edits
     * @return array{0: string, 1: string}
     */
    private function applyEditsToPath(string $path, array $edits): array
    {
        $filePath = $this->normalizePath($this->toAbsolutePath($path));
        $this->assertInsideWorkspace($filePath);

        if ($edits === []) {
            throw new RuntimeException('edits must contain at least one edit');
        }

        $content = $this->loadEditableContent($path, $filePath, $edits);

        foreach ($edits as $edit) {
            $oldString = (string) ($edit['oldString'] ?? '');
            $newString = (string) ($edit['newString'] ?? '');
            $replaceAll = filter_var($edit['replaceAll'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($oldString === $newString) {
                throw new RuntimeException('No changes to apply: oldString and newString are identical.');
            }

            if ($oldString === '') {
                $content = $newString;

                continue;
            }

            $content = $this->replaceContent($content, $oldString, $newString, $replaceAll);
        }

        return [$filePath, $content];
    }

    /**
     * @param  array<int, array{oldString: mixed, newString: mixed, replaceAll?: mixed}>  $edits
     */
    private function loadEditableContent(string $path, string $filePath, array $edits): string
    {
        $firstEdit = $edits[0];
        $firstOldString = (string) ($firstEdit['oldString'] ?? '');

        if (! file_exists($filePath)) {
            if ($firstOldString === '') {
                return '';
            }

            $this->resolveExistingPath($path);
        }

        if (file_exists($filePath) && is_dir($filePath)) {
            throw new RuntimeException("Path is a directory, not a file: {$filePath}");
        }

        if (! file_exists($filePath)) {
            return '';
        }

        $this->ensureTextFile($filePath);

        return File::get($filePath);
    }

    private function replaceContent(string $content, string $oldString, string $newString, bool $replaceAll = false): string
    {
        $notFound = true;

        foreach ($this->replacerChain() as $replacer) {
            foreach ($replacer($content, $oldString) as $search) {
                $index = strpos($content, $search);

                if ($index === false) {
                    continue;
                }

                $notFound = false;

                if ($replaceAll) {
                    return str_replace($search, $newString, $content);
                }

                $lastIndex = strrpos($content, $search);

                if ($lastIndex === false || $index !== $lastIndex) {
                    continue;
                }

                return substr($content, 0, $index).$newString.substr($content, $index + strlen($search));
            }
        }

        if ($notFound) {
            throw new RuntimeException(
                'Could not find oldString in the file. It must match exactly, including whitespace, indentation, and line endings.',
            );
        }

        throw new RuntimeException('Found multiple matches for oldString. Provide more surrounding context to make the match unique.');
    }

    /**
     * @return array<int, callable(string, string): \Generator<int, string, mixed, void>>
     */
    private function replacerChain(): array
    {
        return [
            $this->simpleReplacer(...),
            $this->lineTrimmedReplacer(...),
            $this->blockAnchorReplacer(...),
            $this->whitespaceNormalizedReplacer(...),
            $this->indentationFlexibleReplacer(...),
            $this->escapeNormalizedReplacer(...),
            $this->trimmedBoundaryReplacer(...),
            $this->contextAwareReplacer(...),
            $this->multiOccurrenceReplacer(...),
        ];
    }

    private function simpleReplacer(string $content, string $find): \Generator
    {
        yield $find;
    }

    private function lineTrimmedReplacer(string $content, string $find): \Generator
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);

        if ($searchLines !== [] && end($searchLines) === '') {
            array_pop($searchLines);
        }

        for ($i = 0; $i <= count($originalLines) - count($searchLines); $i++) {
            $matches = true;

            foreach ($searchLines as $j => $searchLine) {
                if (trim($originalLines[$i + $j]) !== trim($searchLine)) {
                    $matches = false;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            $matchLines = array_slice($originalLines, $i, count($searchLines));
            yield implode("\n", $matchLines);
        }
    }

    private function blockAnchorReplacer(string $content, string $find): \Generator
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);

        if (count($searchLines) < 3) {
            return;
        }

        if ($searchLines !== [] && end($searchLines) === '') {
            array_pop($searchLines);
        }

        $firstLine = trim($searchLines[0]);
        $lastLine = trim($searchLines[count($searchLines) - 1]);
        $searchBlockSize = count($searchLines);
        $candidates = [];

        foreach ($originalLines as $i => $line) {
            if (trim($line) !== $firstLine) {
                continue;
            }

            for ($j = $i + 2; $j < count($originalLines); $j++) {
                if (trim($originalLines[$j]) === $lastLine) {
                    $candidates[] = ['start' => $i, 'end' => $j];
                    break;
                }
            }
        }

        if ($candidates === []) {
            return;
        }

        $bestMatch = null;
        $maxSimilarity = count($candidates) === 1 ? 0.0 : -1.0;

        foreach ($candidates as $candidate) {
            $start = $candidate['start'];
            $end = $candidate['end'];
            $actualBlockSize = $end - $start + 1;
            $linesToCheck = min($searchBlockSize - 2, $actualBlockSize - 2);
            $similarity = 1.0;

            if ($linesToCheck > 0) {
                $similarity = 0.0;

                for ($j = 1; $j < $searchBlockSize - 1 && $j < $actualBlockSize - 1; $j++) {
                    $originalLine = trim($originalLines[$start + $j]);
                    $searchLine = trim($searchLines[$j]);
                    $maxLen = max(strlen($originalLine), strlen($searchLine));

                    if ($maxLen === 0) {
                        continue;
                    }

                    $distance = levenshtein($originalLine, $searchLine);
                    $similarity += 1 - ($distance / $maxLen);
                }

                $similarity /= $linesToCheck;
            }

            if (count($candidates) === 1) {
                if ($similarity >= 0.0) {
                    yield implode("\n", array_slice($originalLines, $start, $actualBlockSize));
                }

                return;
            }

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $bestMatch = $candidate;
            }
        }

        if ($bestMatch !== null && $maxSimilarity >= 0.3) {
            yield implode("\n", array_slice($originalLines, $bestMatch['start'], $bestMatch['end'] - $bestMatch['start'] + 1));
        }
    }

    private function whitespaceNormalizedReplacer(string $content, string $find): \Generator
    {
        $normalizeWhitespace = fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', $text));
        $normalizedFind = $normalizeWhitespace($find);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if ($normalizeWhitespace($line) === $normalizedFind) {
                yield $line;

                continue;
            }

            $normalizedLine = $normalizeWhitespace($line);

            if (! str_contains($normalizedLine, $normalizedFind)) {
                continue;
            }

            $words = preg_split('/\s+/', trim($find)) ?: [];

            if ($words === []) {
                continue;
            }

            $pattern = implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words));

            if (preg_match('/'.$pattern.'/', $line, $matches) === 1) {
                yield $matches[0];
            }
        }

        $findLines = explode("\n", $find);

        if (count($findLines) <= 1) {
            return;
        }

        for ($i = 0; $i <= count($lines) - count($findLines); $i++) {
            $block = implode("\n", array_slice($lines, $i, count($findLines)));

            if ($normalizeWhitespace($block) === $normalizedFind) {
                yield $block;
            }
        }
    }

    private function indentationFlexibleReplacer(string $content, string $find): \Generator
    {
        $removeIndentation = function (string $text): string {
            $lines = explode("\n", $text);
            $nonEmptyLines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

            if ($nonEmptyLines === []) {
                return $text;
            }

            $minIndent = min(array_map(function (string $line): int {
                preg_match('/^(\s*)/', $line, $matches);

                return strlen($matches[1] ?? '');
            }, $nonEmptyLines));

            return implode("\n", array_map(
                fn (string $line): string => trim($line) === '' ? $line : substr($line, $minIndent),
                $lines,
            ));
        };

        $normalizedFind = $removeIndentation($find);
        $contentLines = explode("\n", $content);
        $findLines = explode("\n", $find);

        for ($i = 0; $i <= count($contentLines) - count($findLines); $i++) {
            $block = implode("\n", array_slice($contentLines, $i, count($findLines)));

            if ($removeIndentation($block) === $normalizedFind) {
                yield $block;
            }
        }
    }

    private function escapeNormalizedReplacer(string $content, string $find): \Generator
    {
        $unescape = function (string $value): string {
            return preg_replace_callback('/\\\\(n|t|r|\'|"|`|\\\\|\n|\$)/', function (array $matches): string {
                return match ($matches[1]) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    "'" => "'",
                    '"' => '"',
                    '`' => '`',
                    '\\' => '\\',
                    "\n" => "\n",
                    '$' => '$',
                    default => $matches[0],
                };
            }, $value) ?? $value;
        };

        $unescapedFind = $unescape($find);

        if (str_contains($content, $unescapedFind)) {
            yield $unescapedFind;
        }

        $lines = explode("\n", $content);
        $findLines = explode("\n", $unescapedFind);

        for ($i = 0; $i <= count($lines) - count($findLines); $i++) {
            $block = implode("\n", array_slice($lines, $i, count($findLines)));

            if ($unescape($block) === $unescapedFind) {
                yield $block;
            }
        }
    }

    private function trimmedBoundaryReplacer(string $content, string $find): \Generator
    {
        $trimmedFind = trim($find);

        if ($trimmedFind === $find) {
            return;
        }

        if (str_contains($content, $trimmedFind)) {
            yield $trimmedFind;
        }

        $lines = explode("\n", $content);
        $findLines = explode("\n", $find);

        for ($i = 0; $i <= count($lines) - count($findLines); $i++) {
            $block = implode("\n", array_slice($lines, $i, count($findLines)));

            if (trim($block) === $trimmedFind) {
                yield $block;
            }
        }
    }

    private function contextAwareReplacer(string $content, string $find): \Generator
    {
        $findLines = explode("\n", $find);

        if (count($findLines) < 3) {
            return;
        }

        if ($findLines !== [] && end($findLines) === '') {
            array_pop($findLines);
        }

        $contentLines = explode("\n", $content);
        $firstLine = trim($findLines[0]);
        $lastLine = trim($findLines[count($findLines) - 1]);

        for ($i = 0; $i < count($contentLines); $i++) {
            if (trim($contentLines[$i]) !== $firstLine) {
                continue;
            }

            for ($j = $i + 2; $j < count($contentLines); $j++) {
                if (trim($contentLines[$j]) !== $lastLine) {
                    continue;
                }

                $blockLines = array_slice($contentLines, $i, $j - $i + 1);

                if (count($blockLines) !== count($findLines)) {
                    break;
                }

                $matchingLines = 0;
                $nonEmptyLines = 0;

                for ($k = 1; $k < count($blockLines) - 1; $k++) {
                    $blockLine = trim($blockLines[$k]);
                    $findLine = trim($findLines[$k]);

                    if ($blockLine !== '' || $findLine !== '') {
                        $nonEmptyLines++;

                        if ($blockLine === $findLine) {
                            $matchingLines++;
                        }
                    }
                }

                if ($nonEmptyLines === 0 || ($matchingLines / $nonEmptyLines) >= 0.5) {
                    yield implode("\n", $blockLines);
                    break 2;
                }

                break;
            }
        }
    }

    private function multiOccurrenceReplacer(string $content, string $find): \Generator
    {
        $offset = 0;

        while (true) {
            $index = strpos($content, $find, $offset);

            if ($index === false) {
                break;
            }

            yield $find;
            $offset = $index + strlen($find);
        }
    }
}
