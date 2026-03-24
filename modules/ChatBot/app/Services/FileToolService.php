<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services;

use Modules\ChatBot\Services\Concerns\HandlesBashOperations;
use Modules\ChatBot\Services\Concerns\HandlesDiffOperations;
use Modules\ChatBot\Services\Concerns\HandlesDirectoryOperations;
use Modules\ChatBot\Services\Concerns\HandlesLspOperations;
use Modules\ChatBot\Services\Concerns\HandlesPatchOperations;
use Modules\ChatBot\Services\Concerns\HandlesReadOperations;
use Modules\ChatBot\Services\Concerns\HandlesSearchAndMetadataOperations;
use Modules\ChatBot\Services\Concerns\HandlesWriteOperations;
use Modules\ChatBot\Services\Concerns\InteractsWithWorkspacePaths;

class FileToolService
{
    use HandlesBashOperations;
    use HandlesDiffOperations;
    use HandlesDirectoryOperations;
    use HandlesLspOperations;
    use HandlesPatchOperations;
    use HandlesReadOperations;
    use HandlesSearchAndMetadataOperations;
    use HandlesWriteOperations;
    use InteractsWithWorkspacePaths;
}
