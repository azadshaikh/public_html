<?php

namespace Modules\CMS\Events;

use Illuminate\Queue\SerializesModels;
use Modules\CMS\Models\Redirection;

class RedirectionHit
{
    use SerializesModels;

    /**
     * @var Redirection
     */
    public $redirection;

    public function __construct(Redirection $redirection)
    {
        $this->redirection = $redirection;
    }
}
