<?php

namespace Modules\ReleaseManager\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ReleaseManager\Models\Release;

class ReleaseController extends Controller
{
    public function latestUpdate($type, $packageIdentifier, Request $request)
    {
        $response_data = [];
        $latest_release = null;

        foreach ($this->resolvePackageCandidates((string) $type, (string) $packageIdentifier) as $candidatePackageIdentifier) {
            $latest_release = Release::getLatestRelease((string) $type, $candidatePackageIdentifier);
            if (! is_null($latest_release)) {
                break;
            }
        }

        if (! is_null($latest_release)) {
            $response_data = [
                'status' => 'success',
                'release_type' => $latest_release->release_type,
                'package_identifier' => $latest_release->package_identifier,
                'version_type' => strtolower((string) $latest_release->version_type),
                'version' => $latest_release->version,
                'release_date' => $latest_release->release_at,
                'download_link' => $latest_release->release_link,
                'file_name' => $latest_release->file_name,
                'checksum' => $latest_release->checksum,
                'file_size' => $latest_release->file_size,
                'file_size_formatted' => $latest_release->file_size_formatted,
            ];
        } else {
            $response_data = [
                'status' => 'error',
                'message' => 'No update found',
            ];
        }

        return response()->json($response_data, 200);
    }

    /**
     * Keep API backward compatible for legacy application package identifiers.
     *
     * Legacy environments stored application releases under "astero".
     * New environments standardize on "main".
     *
     * @return array<int, string>
     */
    protected function resolvePackageCandidates(string $type, string $packageIdentifier): array
    {
        $candidates = [$packageIdentifier];

        if ($type !== 'application') {
            return $candidates;
        }

        if ($packageIdentifier === 'main') {
            $candidates[] = 'astero';
        } elseif ($packageIdentifier === 'astero') {
            $candidates[] = 'main';
        }

        return array_values(array_unique($candidates));
    }
}
