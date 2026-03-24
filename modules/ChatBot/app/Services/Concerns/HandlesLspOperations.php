<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\ParserFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

trait HandlesLspOperations
{
    private const int LSP_RESULT_LIMIT = 200;

    public function lsp(string $operation, string $filePath, int $line, int $character): string
    {
        $resolvedPath = $this->resolveExistingPath($filePath);

        if (! is_file($resolvedPath)) {
            throw new RuntimeException("Not a file: {$this->relativePath($resolvedPath)}");
        }

        $this->ensureTextFile($resolvedPath);

        $language = $this->detectLspLanguage($resolvedPath);

        if ($language === null) {
            throw new RuntimeException('No LSP server available for this file type.');
        }

        $result = match ($operation) {
            'goToDefinition' => $this->lspGoToDefinition($resolvedPath, $language, $line, $character),
            'findReferences' => $this->lspFindReferences($resolvedPath, $language, $line, $character),
            'hover' => $this->lspHover($resolvedPath, $language, $line, $character),
            'documentSymbol' => $this->lspDocumentSymbol($resolvedPath, $language),
            'workspaceSymbol' => $this->lspWorkspaceSymbol($language),
            'goToImplementation', 'prepareCallHierarchy', 'incomingCalls', 'outgoingCalls' => $this->unsupportedLspOperation($operation, $language),
            default => throw new RuntimeException("Unsupported LSP operation: {$operation}"),
        };

        if (is_string($result)) {
            return $result;
        }

        if ($result === []) {
            return "No results found for {$operation}";
        }

        return (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function diagnosticsSummary(array $paths): string
    {
        $blocks = [];

        foreach (array_values(array_unique($paths)) as $path) {
            $normalizedPath = $this->normalizePath($path);

            if (! $this->isInsideWorkspace($normalizedPath) || ! is_file($normalizedPath)) {
                continue;
            }

            $diagnostics = $this->collectDiagnosticsForFile($normalizedPath);

            if ($diagnostics === []) {
                continue;
            }

            $blocks[] = implode("\n", [
                'LSP errors detected in '.$this->relativePath($normalizedPath).', please fix:',
                '<diagnostics file="'.$normalizedPath.'">',
                ...$diagnostics,
                '</diagnostics>',
            ]);
        }

        return $blocks === [] ? '' : "\n\n".implode("\n\n", $blocks);
    }

    /**
     * @return array<int, string>
     */
    private function collectDiagnosticsForFile(string $filePath): array
    {
        return match ($this->detectLspLanguage($filePath)) {
            'php' => $this->collectPhpDiagnostics($filePath),
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function collectPhpDiagnostics(string $filePath): array
    {
        $process = new Process([PHP_BINARY, '-l', $filePath], $this->workspaceRoot());
        $process->setTimeout(10);
        $process->run();

        if ($process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getErrorOutput()."\n".$process->getOutput());
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $diagnostics = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, 'Errors parsing')) {
                continue;
            }

            $diagnostics[] = $line;
        }

        return $diagnostics !== [] ? $diagnostics : ['PHP syntax check failed.'];
    }

    private function detectLspLanguage(string $filePath): ?string
    {
        $lowerPath = strtolower($filePath);

        return match (true) {
            str_ends_with($lowerPath, '.php') => 'php',
            str_ends_with($lowerPath, '.js'),
            str_ends_with($lowerPath, '.jsx'),
            str_ends_with($lowerPath, '.mjs'),
            str_ends_with($lowerPath, '.cjs') => 'javascript',
            str_ends_with($lowerPath, '.css'),
            str_ends_with($lowerPath, '.scss'),
            str_ends_with($lowerPath, '.sass') => 'css',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>|string
     */
    private function lspGoToDefinition(string $filePath, string $language, int $line, int $character): array|string
    {
        $symbol = $this->symbolAtPosition($filePath, $language, $line, $character);

        if ($symbol === null) {
            return [];
        }

        $results = $this->workspaceSymbolsForName($language, $symbol);

        return $results === [] ? [] : array_slice($results, 0, self::LSP_RESULT_LIMIT);
    }

    /**
     * @return array<int, array<string, mixed>>|string
     */
    private function lspFindReferences(string $filePath, string $language, int $line, int $character): array|string
    {
        $symbol = $this->symbolAtPosition($filePath, $language, $line, $character);

        if ($symbol === null) {
            return [];
        }

        $pattern = $this->referenceRegexForSymbol($symbol, $language);
        $results = [];
        $iterator = $this->createGitignoreFilteredIterator($this->workspaceRoot());

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if ($this->detectLspLanguage($path) !== $language || $this->isBinaryFile($path)) {
                continue;
            }

            $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path) ?: '') ?: [];

            foreach ($lines as $index => $lineText) {
                if (preg_match_all($pattern, $lineText, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                    continue;
                }

                foreach ($matches[0] as [$matchText, $offset]) {
                    $results[] = $this->makeLocationResult(
                        $path,
                        (int) $index,
                        (int) $offset,
                        strlen((string) $matchText),
                    );

                    if (count($results) >= self::LSP_RESULT_LIMIT) {
                        return $results;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function lspHover(string $filePath, string $language, int $line, int $character): array|string
    {
        $symbol = $this->symbolAtPosition($filePath, $language, $line, $character);

        if ($symbol === null) {
            return [];
        }

        $definition = $this->workspaceSymbolsForName($language, $symbol)[0] ?? null;
        $sourcePath = $definition['location']['uri'] ?? null;

        if (is_string($sourcePath) && str_starts_with($sourcePath, 'file://')) {
            $resolvedDefinitionPath = preg_replace('#^file://#', '', $sourcePath) ?: '';
            $resolvedDefinitionPath = rawurldecode($resolvedDefinitionPath);
        } else {
            $resolvedDefinitionPath = $filePath;
        }

        $lineNumber = (int) (($definition['location']['range']['start']['line'] ?? ($line - 1)) + 1);
        $preview = $this->linePreview($resolvedDefinitionPath, $lineNumber);

        return [
            'symbol' => $symbol,
            'language' => $language,
            'filePath' => $resolvedDefinitionPath,
            'line' => $lineNumber,
            'preview' => $preview,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|string
     */
    private function lspDocumentSymbol(string $filePath, string $language): array|string
    {
        return match ($language) {
            'php' => $this->phpDocumentSymbols($filePath),
            'javascript', 'css' => $this->tagLikeDocumentSymbols($filePath, $language),
            default => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>|string
     */
    private function lspWorkspaceSymbol(string $language): array|string
    {
        $results = [];
        $iterator = $this->createGitignoreFilteredIterator($this->workspaceRoot());

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if ($this->detectLspLanguage($path) !== $language || $this->isBinaryFile($path)) {
                continue;
            }

            $symbols = match ($language) {
                'php' => $this->phpWorkspaceSymbols($path),
                'javascript', 'css' => $this->tagLikeWorkspaceSymbols($path, $language),
                default => [],
            };

            foreach ($symbols as $symbol) {
                $results[] = $symbol;

                if (count($results) >= self::LSP_RESULT_LIMIT) {
                    return $results;
                }
            }
        }

        return $results;
    }

    private function unsupportedLspOperation(string $operation, string $language): string
    {
        return sprintf(
            'Operation %s is not yet supported for %s in the current backend.',
            $operation,
            $language,
        );
    }

    private function symbolAtPosition(string $filePath, string $language, int $line, int $character): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($filePath) ?: '') ?: [];
        $lineIndex = $line - 1;

        if (! isset($lines[$lineIndex])) {
            throw new RuntimeException('Line number is outside the file.');
        }

        $lineText = $lines[$lineIndex];
        $offset = max(0, min(strlen($lineText), $character - 1));

        $pattern = $language === 'css'
            ? '/[#.]?[A-Za-z_-][A-Za-z0-9_-]*/'
            : '/\$?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff$]*/u';

        if (preg_match_all($pattern, $lineText, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $candidates = [];

        foreach ($matches[0] as [$match, $position]) {
            $token = (string) $match;
            $start = (int) $position;
            $end = $start + strlen($token);

            $candidates[] = [
                'token' => $token,
                'start' => $start,
                'end' => $end,
                'distance' => $this->tokenDistanceFromOffset($start, $end, $offset),
                'ignorable' => $this->isIgnorableLspToken($token, $language),
            ];

            if ($offset >= $start && $offset <= $end) {
                if (! $this->isIgnorableLspToken($token, $language)) {
                    return $token;
                }
            }
        }

        usort($candidates, function (array $left, array $right): int {
            if ($left['ignorable'] !== $right['ignorable']) {
                return $left['ignorable'] <=> $right['ignorable'];
            }

            return $left['distance'] <=> $right['distance'];
        });

        foreach ($candidates as $candidate) {
            if ($candidate['distance'] > 2 && ! $candidate['ignorable']) {
                break;
            }

            if (! $candidate['ignorable']) {
                return $candidate['token'];
            }
        }

        return null;
    }

    private function tokenDistanceFromOffset(int $start, int $end, int $offset): int
    {
        if ($offset < $start) {
            return $start - $offset;
        }

        if ($offset > $end) {
            return $offset - $end;
        }

        return 0;
    }

    private function isIgnorableLspToken(string $token, string $language): bool
    {
        if ($language !== 'php') {
            return false;
        }

        return in_array(strtolower($token), [
            'public',
            'protected',
            'private',
            'function',
            'new',
            'return',
            'static',
            'use',
            'class',
            'trait',
            'interface',
            'enum',
            'extends',
            'implements',
            'namespace',
            'fn',
        ], true);
    }

    private function referenceRegexForSymbol(string $symbol, string $language): string
    {
        $quoted = preg_quote($symbol, '/');

        if ($language === 'css') {
            return '/'.$quoted.'/';
        }

        if (str_starts_with($symbol, '$')) {
            return '/'.preg_quote($symbol, '/').'/';
        }

        return '/\b'.$quoted.'\b/u';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workspaceSymbolsForName(string $language, string $symbol): array
    {
        $results = $this->lspWorkspaceSymbol($language);

        if (! is_array($results)) {
            return [];
        }

        $normalizedSymbol = $this->normalizeLspSymbolName($symbol);

        return array_values(array_filter(
            $results,
            fn (array $item): bool => $this->normalizeLspSymbolName((string) ($item['name'] ?? '')) === $normalizedSymbol,
        ));
    }

    private function normalizeLspSymbolName(string $symbol): string
    {
        return ltrim($symbol, '$#.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phpDocumentSymbols(string $filePath): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $code = file_get_contents($filePath) ?: '';
        $ast = $parser->parse($code) ?? [];
        $lines = preg_split('/\r\n|\r|\n/', $code) ?: [];
        $symbols = [];

        foreach ($ast as $node) {
            $this->collectPhpSymbols($node, $filePath, $lines, $symbols);
        }

        return $symbols;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phpWorkspaceSymbols(string $filePath): array
    {
        $documentSymbols = $this->phpDocumentSymbols($filePath);

        return array_map(function (array $symbol) use ($filePath): array {
            return [
                'name' => $symbol['name'],
                'kind' => $symbol['kind'],
                'location' => [
                    'uri' => $this->fileUri($filePath),
                    'range' => $symbol['selectionRange'],
                ],
            ];
        }, $documentSymbols);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, array<string, mixed>>  $symbols
     */
    private function collectPhpSymbols(Node $node, string $filePath, array $lines, array &$symbols): void
    {
        $name = null;
        $kind = null;
        $detail = null;

        if ($node instanceof Namespace_) {
            $name = $node->name?->toString();
            $kind = 3;
            $detail = 'namespace';
        } elseif ($node instanceof Class_) {
            $name = $node->name?->toString();
            $kind = 5;
            $detail = 'class';
        } elseif ($node instanceof Interface_) {
            $name = $node->name?->toString();
            $kind = 11;
            $detail = 'interface';
        } elseif ($node instanceof Trait_) {
            $name = $node->name?->toString();
            $kind = 5;
            $detail = 'trait';
        } elseif ($node instanceof Enum_) {
            $name = $node->name?->toString();
            $kind = 10;
            $detail = 'enum';
        } elseif ($node instanceof Function_) {
            $name = $node->name->toString();
            $kind = 12;
            $detail = 'function';
        } elseif ($node instanceof ClassMethod) {
            $name = $node->name->toString();
            $kind = 6;
            $detail = 'method';
        } elseif ($node instanceof Property) {
            $name = $node->props[0]->name->toString();
            $kind = 7;
            $detail = 'property';
        } elseif ($node instanceof ClassConst) {
            $name = $node->consts[0]->name->toString();
            $kind = 14;
            $detail = 'constant';
        }

        if (is_string($name) && $name !== '' && is_int($kind)) {
            $symbols[] = [
                'name' => $name,
                'detail' => $detail,
                'kind' => $kind,
                'range' => $this->nodeRange($node, $lines, $name),
                'selectionRange' => $this->nodeRange($node, $lines, $name),
            ];
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->collectPhpSymbols($subNode, $filePath, $lines, $symbols);
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectPhpSymbols($child, $filePath, $lines, $symbols);
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<string, array<string, int>>
     */
    private function nodeRange(Node $node, array $lines, string $name): array
    {
        $startLine = max(0, $node->getStartLine() - 1);
        $endLine = max(0, $node->getEndLine() - 1);
        $lineText = $lines[$startLine] ?? '';
        $startChar = strpos($lineText, $name);
        $startChar = $startChar === false ? 0 : $startChar;

        return [
            'start' => ['line' => $startLine, 'character' => $startChar],
            'end' => ['line' => $endLine, 'character' => $startChar + strlen($name)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tagLikeDocumentSymbols(string $filePath, string $language): array
    {
        $symbols = $this->ctagsDocumentSymbols($filePath);

        if ($symbols !== []) {
            return $symbols;
        }

        return $language === 'javascript'
            ? $this->regexJavascriptDocumentSymbols($filePath)
            : $this->regexCssDocumentSymbols($filePath);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tagLikeWorkspaceSymbols(string $filePath, string $language): array
    {
        return array_map(function (array $symbol) use ($filePath): array {
            return [
                'name' => $symbol['name'],
                'kind' => $symbol['kind'],
                'location' => [
                    'uri' => $this->fileUri($filePath),
                    'range' => $symbol['selectionRange'],
                ],
            ];
        }, $this->tagLikeDocumentSymbols($filePath, $language));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ctagsDocumentSymbols(string $filePath): array
    {
        $binary = $this->resolveCtagsBinary();

        if ($binary === null) {
            return [];
        }

        $process = new Process([
            $binary,
            '--output-format=json',
            '--fields=+nK',
            '--extras=-F',
            '-o',
            '-',
            $filePath,
        ], $this->workspaceRoot());
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $results = [];

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $payload = json_decode($line, true);

            if (! is_array($payload) || ($payload['_type'] ?? null) !== 'tag' || ! isset($payload['name'], $payload['line'])) {
                continue;
            }

            $name = (string) $payload['name'];
            $lineNumber = max(0, ((int) $payload['line']) - 1);
            $kind = $this->ctagsKindToLspKind((string) ($payload['kind'] ?? ''));
            $startChar = 0;

            $results[] = [
                'name' => $name,
                'detail' => $payload['kind'] ?? null,
                'kind' => $kind,
                'range' => [
                    'start' => ['line' => $lineNumber, 'character' => $startChar],
                    'end' => ['line' => $lineNumber, 'character' => $startChar + strlen($name)],
                ],
                'selectionRange' => [
                    'start' => ['line' => $lineNumber, 'character' => $startChar],
                    'end' => ['line' => $lineNumber, 'character' => $startChar + strlen($name)],
                ],
            ];
        }

        return $results;
    }

    private function ctagsKindToLspKind(string $kind): int
    {
        return match (strtolower($kind)) {
            'class' => 5,
            'function', 'func' => 12,
            'method' => 6,
            'property' => 7,
            'constant', 'const' => 14,
            'interface' => 11,
            'enum' => 10,
            default => 13,
        };
    }

    private function resolveCtagsBinary(): ?string
    {
        foreach (['/usr/bin/ctags', '/usr/bin/universal-ctags', '/bin/ctags'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regexJavascriptDocumentSymbols(string $filePath): array
    {
        $symbols = [];
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($filePath) ?: '') ?: [];

        foreach ($lines as $index => $line) {
            $patterns = [
                ['pattern' => '/^\s*export\s+(?:default\s+)?class\s+([A-Za-z_$][A-Za-z0-9_$]*)/', 'kind' => 5, 'detail' => 'class'],
                ['pattern' => '/^\s*(?:export\s+)?function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/', 'kind' => 12, 'detail' => 'function'],
                ['pattern' => '/^\s*(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=/', 'kind' => 13, 'detail' => 'variable'],
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern['pattern'], $line, $matches) !== 1) {
                    continue;
                }

                $name = $matches[1];
                $startChar = strpos($line, $name);
                $startChar = $startChar === false ? 0 : $startChar;

                $symbols[] = [
                    'name' => $name,
                    'detail' => $pattern['detail'],
                    'kind' => $pattern['kind'],
                    'range' => [
                        'start' => ['line' => $index, 'character' => $startChar],
                        'end' => ['line' => $index, 'character' => $startChar + strlen($name)],
                    ],
                    'selectionRange' => [
                        'start' => ['line' => $index, 'character' => $startChar],
                        'end' => ['line' => $index, 'character' => $startChar + strlen($name)],
                    ],
                ];
            }
        }

        return $symbols;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regexCssDocumentSymbols(string $filePath): array
    {
        $symbols = [];
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($filePath) ?: '') ?: [];

        foreach ($lines as $index => $line) {
            if (! str_contains($line, '{') || str_starts_with(trim($line), '@')) {
                continue;
            }

            [$selectorPart] = explode('{', $line, 2);

            foreach (array_filter(array_map('trim', explode(',', $selectorPart))) as $selector) {
                if ($selector === '') {
                    continue;
                }

                $startChar = strpos($line, $selector);
                $startChar = $startChar === false ? 0 : $startChar;

                $symbols[] = [
                    'name' => $selector,
                    'detail' => 'selector',
                    'kind' => 13,
                    'range' => [
                        'start' => ['line' => $index, 'character' => $startChar],
                        'end' => ['line' => $index, 'character' => $startChar + strlen($selector)],
                    ],
                    'selectionRange' => [
                        'start' => ['line' => $index, 'character' => $startChar],
                        'end' => ['line' => $index, 'character' => $startChar + strlen($selector)],
                    ],
                ];
            }
        }

        return $symbols;
    }

    private function fileUri(string $filePath): string
    {
        return 'file://'.str_replace('%2F', '/', rawurlencode($filePath));
    }

    private function linePreview(string $filePath, int $lineNumber): string
    {
        if (! is_file($filePath)) {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($filePath) ?: '') ?: [];
        $line = $lines[$lineNumber - 1] ?? '';

        return trim($line);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeLocationResult(string $filePath, int $line, int $character, int $length): array
    {
        return [
            'uri' => $this->fileUri($filePath),
            'range' => [
                'start' => ['line' => $line, 'character' => $character],
                'end' => ['line' => $line, 'character' => $character + $length],
            ],
        ];
    }
}
