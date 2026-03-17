<?php

namespace Modules\Platform\Exceptions;

use Exception;

/**
 * Thrown by provisioning step commands to signal a WAITING state.
 *
 * When caught by BaseCommand::handle(), this results in exit code 2
 * instead of the normal SUCCESS (0) or thrown-exception failure path.
 * The orchestrator interprets exit code 2 as "pause pipeline, will resume later."
 */
class WaitingException extends Exception {}
