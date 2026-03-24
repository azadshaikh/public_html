<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\ChatBot\Ai\Tools\Concerns\HasToolArgumentParsing;
use Modules\ChatBot\Services\FileToolService;
use RuntimeException;
use Stringable;

class LspTool implements Tool
{
    use HasToolArgumentParsing;

    /**
     * @var array<int, string>
     */
    private const array OPERATIONS = [
        'goToDefinition',
        'findReferences',
        'hover',
        'documentSymbol',
        'workspaceSymbol',
        'goToImplementation',
        'prepareCallHierarchy',
        'incomingCalls',
        'outgoingCalls',
    ];

    public function name(): string
    {
        return 'lsp';
    }

    public function description(): Stringable|string
    {
        return implode("\n", [
            'Interact with Language Server Protocol (LSP) servers to get code intelligence features.',
            '',
            'Supported operations:',
            '- goToDefinition: Find where a symbol is defined',
            '- findReferences: Find all references to a symbol',
            '- hover: Get hover information (documentation, type info) for a symbol',
            '- documentSymbol: Get all symbols (functions, classes, variables) in a document',
            '- workspaceSymbol: Search for symbols across the entire workspace',
            '- goToImplementation: Find implementations of an interface or abstract method',
            '- prepareCallHierarchy: Get call hierarchy item at a position (functions/methods)',
            '- incomingCalls: Find all functions/methods that call the function at a position',
            '- outgoingCalls: Find all functions/methods called by the function at a position',
            '',
            'All operations require:',
            '- filePath: The file to operate on',
            '- line: The line number (1-based, as shown in editors)',
            '- character: The character offset (1-based, as shown in editors)',
            '',
            'Current backend scope: PHP has the strongest support. JavaScript and CSS use a best-effort backend. Unsupported operations or file types return explicit messages.',
        ]);
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $operation = trim((string) ($data['operation'] ?? ''));
        $filePath = trim((string) ($data['filePath'] ?? ''));
        $line = (int) ($data['line'] ?? 0);
        $character = (int) ($data['character'] ?? 0);

        return $this->executeWithLogging(
            arguments: [
                'operation' => $operation,
                'filePath' => $filePath,
                'line' => $line,
                'character' => $character,
            ],
            operation: function () use ($operation, $filePath, $line, $character): string {
                if ($operation === '') {
                    throw new RuntimeException('operation is required');
                }

                if (! in_array($operation, self::OPERATIONS, true)) {
                    throw new RuntimeException("Unsupported operation: {$operation}");
                }

                if ($filePath === '') {
                    throw new RuntimeException('filePath is required');
                }

                if ($line < 1) {
                    throw new RuntimeException('line must be at least 1');
                }

                if ($character < 1) {
                    throw new RuntimeException('character must be at least 1');
                }

                return app(FileToolService::class)->lsp(
                    operation: $operation,
                    filePath: $filePath,
                    line: $line,
                    character: $character,
                );
            },
            errorPrefix: 'Error running lsp',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()
                ->description('The LSP operation to perform')
                ->enum(self::OPERATIONS)
                ->required(),
            'filePath' => $schema->string()
                ->description('The absolute or relative path to the file')
                ->required(),
            'line' => $schema->integer()
                ->description('The line number (1-based, as shown in editors)')
                ->required(),
            'character' => $schema->integer()
                ->description('The character offset (1-based, as shown in editors)')
                ->required(),
        ];
    }
}
