<?php

declare(strict_types=1);

namespace Modules\Agency\Services;

use App\Scaffold\ScaffoldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Agency\Definitions\AgencyTicketDefinition;
use Modules\Agency\Http\Resources\AgencyTicketResource;
use Modules\Helpdesk\Services\TicketService;

class AgencyTicketService extends TicketService
{
    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new AgencyTicketDefinition;
    }

    protected function getResourceClass(): ?string
    {
        return AgencyTicketResource::class;
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Scope to current user strictly
        $query->where('user_id', auth()->id())
            ->latest('updated_at');
    }
}
