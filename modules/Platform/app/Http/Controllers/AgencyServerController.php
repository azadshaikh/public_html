<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Platform\Http\Requests\AgencyAttachServersRequest;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;

class AgencyServerController extends Controller
{
    /**
     * Get servers associated with an agency
     */
    public function getServers($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        /** @var Collection<int, Server> $serversCollection */
        $serversCollection = $agency->servers()
            ->withPivot('is_primary')
            ->get();

        $servers = [];
        foreach ($serversCollection as $server) {
            $servers[] = [
                'id' => $server->id,
                'name' => $server->name,
                'href' => route('platform.servers.show', $server),
                'subtitle' => $server->ip,
                'type' => $server->type,
                'type_label' => $server->type_label,
                'status' => $server->status,
                'status_label' => $server->status_label,
                'is_primary' => (bool) ($server->pivot->is_primary ?? false),
                'can_remove' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'servers' => $servers,
        ]);
    }

    /**
     * Attach servers to an agency
     */
    public function attachServers(AgencyAttachServersRequest $request, $id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $serverIds = $request->input('server_ids', []);
        $primaryServerId = $request->input('primary_server_id');

        // Prepare sync data with is_primary flag
        $syncData = [];
        foreach ($serverIds as $serverId) {
            $syncData[$serverId] = [
                'is_primary' => ($serverId === $primaryServerId),
            ];
        }

        // Sync servers (this will detach any not in the list and attach new ones)
        $agency->servers()->sync($syncData);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['server_ids' => $serverIds, 'primary_server_id' => $primaryServerId])
            ->log('updated_agency_servers');

        return response()->json([
            'success' => true,
            'message' => 'Agency servers updated successfully',
        ]);
    }

    /**
     * Detach a server from an agency
     */
    public function detachServer($id, $server): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);
        /** @var Server $serverModel */
        $serverModel = Server::query()->findOrFail($server);

        $agency->servers()->detach($serverModel->id);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['server_id' => $serverModel->id])
            ->log('detached_server_from_agency');

        return response()->json([
            'success' => true,
            'message' => 'Server removed from agency',
        ]);
    }

    /**
     * Set primary server for an agency
     */
    public function setPrimaryServer($id, $server): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);
        /** @var Server $serverModel */
        $serverModel = Server::query()->findOrFail($server);

        if (! $agency->servers()->where('platform_servers.id', $serverModel->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Server is not attached to this agency',
            ], 422);
        }

        $attachedServerIds = $agency->servers()->pluck('platform_servers.id');

        foreach ($attachedServerIds as $attachedServerId) {
            $agency->servers()->updateExistingPivot($attachedServerId, [
                'is_primary' => ((int) $attachedServerId === (int) $serverModel->id),
            ]);
        }

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['server_id' => $serverModel->id])
            ->log('set_primary_server');

        return response()->json([
            'success' => true,
            'message' => 'Primary server updated',
        ]);
    }

    /**
     * Get available servers that can be associated with this agency
     */
    public function getAvailableServers($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $availableServers = Server::query()->whereNotIn('id', $agency->servers->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'ip', 'type', 'status']);

        $servers = [];
        foreach ($availableServers as $server) {
            $servers[] = [
                'id' => $server->id,
                'name' => $server->name,
                'href' => route('platform.servers.show', $server),
                'subtitle' => $server->ip,
                'type' => $server->type,
                'type_label' => $server->type_label,
                'status' => $server->status,
                'status_label' => $server->status_label,
            ];
        }

        return response()->json([
            'success' => true,
            'servers' => $servers,
        ]);
    }
}
