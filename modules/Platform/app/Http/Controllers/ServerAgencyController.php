<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Platform\Http\Requests\ServerAttachAgenciesRequest;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;

class ServerAgencyController extends Controller
{
    /**
     * Get agencies associated with a server
     */
    public function getAgencies($id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail($id);

        $agencies = $server->agencies()
            ->withPivot('is_primary')
            ->get()
            ->map(function ($agency): array {
                $agencyData = $agency->toArray();

                return [
                    'id' => (int) ($agencyData['id'] ?? 0),
                    'name' => (string) ($agencyData['name'] ?? ''),
                    'is_primary' => (bool) data_get($agencyData, 'pivot.is_primary', false),
                    'can_remove' => true,
                ];
            });

        return response()->json([
            'success' => true,
            'agencies' => $agencies,
        ]);
    }

    /**
     * Attach agencies to a server
     */
    public function attachAgencies(ServerAttachAgenciesRequest $request, $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail($id);

        $agencyIds = $request->input('agency_ids', []);
        $primaryAgencyId = $request->input('primary_agency_id');

        // Prepare sync data with is_primary flag
        $syncData = [];
        foreach ($agencyIds as $agencyId) {
            $syncData[$agencyId] = [
                'is_primary' => ($agencyId === $primaryAgencyId),
            ];
        }

        // Sync agencies (this will detach any not in the list and attach new ones)
        $server->agencies()->sync($syncData);

        activity()
            ->performedOn($server)
            ->causedBy(auth()->user())
            ->withProperties(['agency_ids' => $agencyIds, 'primary_agency_id' => $primaryAgencyId])
            ->log('updated_server_agencies');

        return response()->json([
            'success' => true,
            'message' => 'Server agencies updated successfully',
        ]);
    }

    /**
     * Detach an agency from a server
     */
    public function detachAgency($id, $agency): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail($id);
        /** @var Agency $agencyModel */
        $agencyModel = Agency::query()->findOrFail($agency);

        $server->agencies()->detach($agencyModel->id);

        activity()
            ->performedOn($server)
            ->causedBy(auth()->user())
            ->withProperties(['agency_id' => $agencyModel->id])
            ->log('detached_agency_from_server');

        return response()->json([
            'success' => true,
            'message' => 'Agency removed from server',
        ]);
    }

    /**
     * Set primary agency for a server
     */
    public function setPrimaryAgency($id, $agency): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail($id);
        /** @var Agency $agencyModel */
        $agencyModel = Agency::query()->findOrFail($agency);

        // First, remove is_primary from all agencies for this server
        $server->agencies()->updateExistingPivot(
            $server->agencies()->pluck('platform_agencies.id'),
            ['is_primary' => false]
        );

        // Then set the selected agency as primary
        $server->agencies()->updateExistingPivot($agencyModel->id, ['is_primary' => true]);

        activity()
            ->performedOn($server)
            ->causedBy(auth()->user())
            ->withProperties(['agency_id' => $agencyModel->id])
            ->log('set_primary_agency');

        return response()->json([
            'success' => true,
            'message' => 'Primary agency updated',
        ]);
    }

    /**
     * Get available agencies for selection
     */
    public function getAvailableAgencies($id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail($id);

        $attachedIds = $server->agencies()->pluck('platform_agencies.id')->toArray();

        $available = Agency::query()->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'agencies' => $available,
        ]);
    }
}
