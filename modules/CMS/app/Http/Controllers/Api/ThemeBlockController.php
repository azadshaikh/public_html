<?php

namespace Modules\CMS\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CMS\Services\Builder\ThemeBlockService;

class ThemeBlockController extends Controller
{
    public function __construct(
        protected ThemeBlockService $themeBlockService
    ) {}

    /**
     * Get manifest of all blocks OR sections in a theme
     *
     * @param  string  $type  'blocks' or 'sections'
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'theme' => ['required', 'string'],
        ]);

        $theme = $request->input('theme');

        // Security check: theme name should be alphanumeric/dashes
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $theme)) {
            return response()->json(['error' => 'Invalid theme name'], 400);
        }

        // Validate type
        if (! in_array($type, ['blocks', 'sections'])) {
            return response()->json(['error' => 'Invalid type. Must be blocks or sections.'], 400);
        }

        $manifest = $this->themeBlockService->getManifest($theme, $type);

        return response()->json($manifest);
    }

    /**
     * Render a block/section via Twig
     *
     * @param  string  $type  'blocks' or 'sections'
     */
    public function render(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'theme' => ['required', 'string'],
            'slug' => ['required', 'string'],
        ]);

        $theme = $request->input('theme');
        $slug = $request->input('slug'); // e.g. "hero/hero-gradient"

        // Security check: theme name should be alphanumeric/dashes
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $theme)) {
            return response()->json(['error' => 'Invalid theme name'], 400);
        }

        // Security check: slug must be category/name format with safe characters only
        // Prevents path traversal attacks (e.g., ../../../etc/passwd)
        if (! preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+$/', (string) $slug)) {
            return response()->json(['error' => 'Invalid slug format'], 400);
        }

        // Validate type
        if (! in_array($type, ['blocks', 'sections'])) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $result = $this->themeBlockService->renderForBuilder($theme, $type, $slug);

        return response()->json($result);
    }
}
