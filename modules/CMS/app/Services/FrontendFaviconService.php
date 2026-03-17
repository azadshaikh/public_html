<?php

namespace Modules\CMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;
use Modules\CMS\Models\Theme;
use Throwable;

class FrontendFaviconService
{
    private const array GENERATED_PUBLIC_FILES = [
        'favicon.ico',
        'favicon.svg',
        'favicon-96x96.png',
        'apple-touch-icon.png',
        'web-app-manifest-192x192.png',
        'web-app-manifest-512x512.png',
        'site.webmanifest',
    ];

    /**
     * Transparent 1x1 PNG fallback.
     */
    private const string FALLBACK_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8F4S0AAAAASUVORK5CYII=';

    /**
     * Allowed image file extensions for favicon values.
     */
    private const array ALLOWED_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif',
    ];

    public function renderHeadMarkup(): string
    {
        if (! $this->shouldInjectGeneratedMarkup()) {
            return '';
        }

        $assetVersion = $this->resolveAssetVersionForMarkup();
        $manifestVersion = $this->resolveManifestVersionForMarkup($assetVersion);
        $themeColor = $this->resolveThemeColor();
        $siteTitle = e($this->resolveSiteTitle());

        $markup = [
            '',
            '<!-- cms-auto-favicon:start -->',
        ];

        if (is_file(public_path('favicon.svg'))) {
            $svgUrl = $this->buildFrontendAssetUrl('/favicon.svg', $assetVersion);
            $markup[] = '<link id="cms-auto-favicon-svg" rel="icon" type="image/svg+xml" href="'.$svgUrl.'" />';
        }

        if (is_file(public_path('favicon-96x96.png'))) {
            $png96Url = $this->buildFrontendAssetUrl('/favicon-96x96.png', $assetVersion);
            $markup[] = '<link id="cms-auto-favicon-96" rel="icon" type="image/png" sizes="96x96" href="'.$png96Url.'" />';
        }

        if (is_file(public_path('favicon.ico'))) {
            $icoUrl = $this->buildFrontendAssetUrl('/favicon.ico', $assetVersion);
            $markup[] = '<link id="cms-auto-favicon-ico" rel="shortcut icon" href="'.$icoUrl.'" />';
        }

        if (is_file(public_path('apple-touch-icon.png'))) {
            $appleUrl = $this->buildFrontendAssetUrl('/apple-touch-icon.png', $assetVersion);
            $markup[] = '<link id="cms-auto-favicon-apple" rel="apple-touch-icon" sizes="180x180" href="'.$appleUrl.'" />';
        }

        $markup[] = '<meta id="cms-auto-favicon-title" name="apple-mobile-web-app-title" content="'.$siteTitle.'" />';

        if (is_file(public_path('site.webmanifest'))) {
            $manifestUrl = $this->buildFrontendAssetUrl('/site.webmanifest', $manifestVersion);
            $markup[] = '<link id="cms-auto-favicon-manifest" rel="manifest" href="'.$manifestUrl.'" />';
        }

        $markup[] = '<meta id="cms-auto-favicon-theme-color" name="theme-color" content="'.$themeColor.'" />';
        $markup[] = '<!-- cms-auto-favicon:end -->';

        return implode("\n", $markup)."\n";
    }

    public function syncGeneratedAssets(): void
    {
        if (! $this->hasCustomFaviconConfigured()) {
            $this->deleteGeneratedAssets();
            $this->forgetGeneratedAssetState();

            return;
        }

        $version = $this->getCacheVersion();
        $this->generateAndPersist($version);
        $this->prepareManifest($version);
    }

    public function getCacheVersion(): string
    {
        return substr(sha1($this->getSourceSignature()), 0, 16);
    }

    private function allPublicAssetsExist(): bool
    {
        $required = [
            'favicon.ico',
            'favicon.svg',
            'favicon-96x96.png',
            'apple-touch-icon.png',
            'web-app-manifest-192x192.png',
            'web-app-manifest-512x512.png',
        ];

        foreach ($required as $file) {
            if (! is_file(public_path($file))) {
                return false;
            }
        }

        return true;
    }

    private function generateAndPersist(string $version): void
    {
        $source = $this->resolveSourceImage();

        $assets = [
            'favicon.svg' => $this->buildVariantPayload($source, 'svg'),
            'favicon.ico' => $this->buildVariantPayload($source, 'ico'),
            'favicon-96x96.png' => $this->buildVariantPayload($source, '96'),
            'apple-touch-icon.png' => $this->buildVariantPayload($source, '180'),
            'web-app-manifest-192x192.png' => $this->buildVariantPayload($source, '192'),
            'web-app-manifest-512x512.png' => $this->buildVariantPayload($source, '512'),
        ];

        foreach ($assets as $filename => $payload) {
            $decoded = base64_decode($payload['body']);
            if ($decoded === '') {
                Log::warning('Generated favicon payload was empty', [
                    'file' => $filename,
                ]);

                continue;
            }

            $this->writeFile(public_path($filename), $decoded);
        }

        Cache::forever('cms_frontend_favicon_public_version', $version);
        Cache::forever('cms_frontend_favicon_source_signature', $this->getSourceSignature());
    }

    private function prepareManifest(string $assetVersion): string
    {
        $manifestVersion = $this->getManifestVersion($assetVersion);

        $manifest = $this->buildManifestJson($assetVersion);
        $this->writeFile(public_path('site.webmanifest'), $manifest);
        Cache::forever('cms_frontend_favicon_manifest_version', $manifestVersion);

        return $manifestVersion;
    }

    private function getManifestVersion(string $assetVersion): string
    {
        $themeColor = $this->resolveThemeColor();
        $backgroundColor = $this->resolveBackgroundColor();
        $siteTitle = $this->resolveSiteTitle();

        return substr(sha1($assetVersion.'|'.$themeColor.'|'.$backgroundColor.'|'.$siteTitle), 0, 16);
    }

    private function shouldInjectGeneratedMarkup(): bool
    {
        return $this->hasCustomFaviconConfigured() && $this->hasAnyGeneratedAsset();
    }

    private function hasCustomFaviconConfigured(): bool
    {
        return $this->getCustomFaviconReference() !== null;
    }

    private function hasAnyGeneratedAsset(): bool
    {
        foreach (self::GENERATED_PUBLIC_FILES as $file) {
            if (is_file(public_path($file))) {
                return true;
            }
        }

        return false;
    }

    private function resolveAssetVersionForMarkup(): string
    {
        $cachedVersion = Cache::get('cms_frontend_favicon_public_version');

        return is_string($cachedVersion) && $cachedVersion !== ''
            ? $cachedVersion
            : $this->getCacheVersion();
    }

    private function resolveManifestVersionForMarkup(string $assetVersion): string
    {
        $cachedVersion = Cache::get('cms_frontend_favicon_manifest_version');

        return is_string($cachedVersion) && $cachedVersion !== ''
            ? $cachedVersion
            : $this->getManifestVersion($assetVersion);
    }

    private function deleteGeneratedAssets(): void
    {
        foreach (self::GENERATED_PUBLIC_FILES as $file) {
            $path = public_path($file);

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function forgetGeneratedAssetState(): void
    {
        Cache::forget('cms_frontend_favicon_public_version');
        Cache::forget('cms_frontend_favicon_manifest_version');
        Cache::forget('cms_frontend_favicon_source_signature');
    }

    private function writeFile(string $path, string $contents): void
    {
        if (is_file($path)) {
            $existingContents = @file_get_contents($path);

            if (is_string($existingContents) && $existingContents === $contents) {
                return;
            }
        }

        $bytes = @file_put_contents($path, $contents, LOCK_EX);

        if ($bytes === false) {
            Log::warning('Failed to write generated favicon asset', [
                'path' => $path,
            ]);
        }
    }

    /**
     * @param  array{mime: string, bytes: string, reference: string}  $source
     * @return array{mime: string, body: string}
     */
    private function buildVariantPayload(array $source, string $variant): array
    {
        return match ($variant) {
            'svg' => $this->buildSvgPayload($source),
            'ico' => $this->buildRasterPayload($source, 48, true),
            '96' => $this->buildRasterPayload($source, 96, false),
            '180' => $this->buildRasterPayload($source, 180, false),
            '192' => $this->buildRasterPayload($source, 192, false),
            '512' => $this->buildRasterPayload($source, 512, false),
            default => $this->fallbackPayload('image/png'),
        };
    }

    /**
     * @return array{mime: string, bytes: string, reference: string}
     */
    private function resolveSourceImage(): array
    {
        $sourceSignature = $this->getSourceSignature();
        $cacheKey = 'cms_frontend_favicon_source_'.sha1($sourceSignature);

        /** @var array{mime: string, bytes: string, reference: string}|null $source */
        $source = Cache::remember($cacheKey, now()->addHours(6), function (): ?array {
            $reference = $this->getCustomFaviconReference();

            if ($reference !== null) {
                $loaded = $this->loadReferenceImage($reference);
                if ($loaded !== null) {
                    return $loaded;
                }
            }

            $brandingReference = $this->getBrandingIconReference();
            if ($brandingReference !== null) {
                $loaded = $this->loadReferenceImage($brandingReference);
                if ($loaded !== null) {
                    return $loaded;
                }
            }

            $fallbackPath = $this->resolveThemeFallbackIconPath();
            if ($fallbackPath !== null) {
                $fallbackBytes = @file_get_contents($fallbackPath);
                if (is_string($fallbackBytes) && $fallbackBytes !== '') {
                    $fallbackMime = $this->detectMimeType($fallbackPath, $fallbackBytes);
                    if (str_starts_with($fallbackMime, 'image/')) {
                        return [
                            'mime' => $fallbackMime,
                            'bytes' => $fallbackBytes,
                            'reference' => $fallbackPath,
                        ];
                    }
                }
            }

            return null;
        });

        if ($source !== null) {
            return $source;
        }

        return [
            'mime' => 'image/png',
            'bytes' => base64_decode(self::FALLBACK_PNG_BASE64) ?: '',
            'reference' => 'fallback-transparent',
        ];
    }

    /**
     * @param  array{mime: string, bytes: string, reference: string}  $source
     * @return array{mime: string, body: string}
     */
    private function buildSvgPayload(array $source): array
    {
        if ($source['mime'] === 'image/svg+xml') {
            return [
                'mime' => 'image/svg+xml',
                'body' => base64_encode($source['bytes']),
            ];
        }

        $dataUri = 'data:'.$source['mime'].';base64,'.base64_encode($source['bytes']);
        $escapedDataUri = e($dataUri);
        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
    <image href="{$escapedDataUri}" width="512" height="512" preserveAspectRatio="xMidYMid slice" />
</svg>
SVG;

        return [
            'mime' => 'image/svg+xml',
            'body' => base64_encode($svg),
        ];
    }

    /**
     * @param  array{mime: string, bytes: string, reference: string}  $source
     * @return array{mime: string, body: string}
     */
    private function buildRasterPayload(array $source, int $size, bool $asIco): array
    {
        $imagick = $this->rasterizeWithImagick($source['bytes'], $size, $asIco);
        if ($imagick !== null) {
            return [
                'mime' => $asIco ? 'image/x-icon' : 'image/png',
                'body' => base64_encode($imagick),
            ];
        }

        $gd = $this->rasterizeWithGd($source['bytes'], $size);
        if ($gd !== null) {
            return [
                'mime' => $asIco ? 'image/x-icon' : 'image/png',
                'body' => base64_encode($gd),
            ];
        }

        // If conversion failed, return source only when already icon-like.
        if (in_array($source['mime'], ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'], true)) {
            return [
                'mime' => $asIco ? 'image/x-icon' : $source['mime'],
                'body' => base64_encode($source['bytes']),
            ];
        }

        return $this->fallbackPayload($asIco ? 'image/x-icon' : 'image/png');
    }

    /**
     * @return array{mime: string, body: string}
     */
    private function fallbackPayload(string $mime): array
    {
        return [
            'mime' => $mime,
            'body' => self::FALLBACK_PNG_BASE64,
        ];
    }

    private function rasterizeWithImagick(string $bytes, int $size, bool $asIco): ?string
    {
        if (! extension_loaded('imagick') || ! class_exists(Imagick::class)) {
            return null;
        }

        try {
            $image = new Imagick;
            $image->readImageBlob($bytes);
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $image->setBackgroundColor(new ImagickPixel('transparent'));
            $image->thumbnailImage($size, $size, true, true);
            $image->setImageFormat($asIco ? 'ico' : 'png');
            $output = $image->getImagesBlob();
            $image->clear();
            $image->destroy();

            return $output !== '' ? $output : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function rasterizeWithGd(string $bytes, int $size): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $sourceImage = @imagecreatefromstring($bytes);
        if (! $sourceImage) {
            return null;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) { // @phpstan-ignore smallerOrEqual.alwaysFalse, smallerOrEqual.alwaysFalse, booleanOr.alwaysFalse
            imagedestroy($sourceImage);

            return null;
        }

        $destination = imagecreatetruecolor($size, $size);
        if (! $destination) {
            imagedestroy($sourceImage);

            return null;
        }

        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefill($destination, 0, 0, $transparent);

        // Center-crop to keep icon composition consistent.
        $scale = max($size / $sourceWidth, $size / $sourceHeight);
        $cropWidth = (int) round($size / $scale);
        $cropHeight = (int) round($size / $scale);
        $sourceX = (int) max(0, floor(($sourceWidth - $cropWidth) / 2));
        $sourceY = (int) max(0, floor(($sourceHeight - $cropHeight) / 2));

        imagecopyresampled(
            $destination,
            $sourceImage,
            0,
            0,
            $sourceX,
            $sourceY,
            $size,
            $size,
            $cropWidth,
            $cropHeight
        );

        ob_start();
        imagepng($destination, null, 9);
        $png = ob_get_clean();

        imagedestroy($destination);
        imagedestroy($sourceImage);

        return is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * @return array{mime: string, bytes: string, reference: string}|null
     */
    private function loadReferenceImage(string $reference): ?array
    {
        $localPath = $this->resolveLocalPathFromReference($reference);
        if ($localPath !== null) {
            $bytes = @file_get_contents($localPath);
            if (! is_string($bytes) || $bytes === '') {
                return null;
            }

            $mime = $this->detectMimeType($localPath, $bytes);
            if (! str_starts_with($mime, 'image/')) {
                return null;
            }

            return [
                'mime' => $mime,
                'bytes' => $bytes,
                'reference' => $reference,
            ];
        }

        if (! $this->isAbsoluteHttpUrl($reference)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->accept('image/*')->get($reference);
            if (! $response->ok()) {
                return null;
            }

            $bytes = $response->body();
            if ($bytes === '') {
                return null;
            }

            $mime = (string) ($response->header('Content-Type') ?: 'application/octet-stream');
            $mime = strtolower(trim(explode(';', $mime)[0]));

            if (! str_starts_with($mime, 'image/')) {
                $mime = $this->detectMimeType(null, $bytes);
            }

            if (! str_starts_with($mime, 'image/')) {
                return null;
            }

            return [
                'mime' => $mime,
                'bytes' => $bytes,
                'reference' => $reference,
            ];
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch remote favicon reference', [
                'reference' => $reference,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    private function getSourceSignature(): string
    {
        $customReference = $this->getCustomFaviconReference();
        if ($customReference !== null) {
            return 'custom:'.$customReference;
        }

        $brandingReference = $this->getBrandingIconReference();
        if ($brandingReference !== null) {
            return 'branding-icon:'.$brandingReference;
        }

        $fallbackPath = $this->resolveThemeFallbackIconPath();
        if ($fallbackPath !== null) {
            $mtime = (string) @filemtime($fallbackPath);

            return 'theme-fallback:'.$fallbackPath.':'.$mtime;
        }

        return 'fallback:transparent';
    }

    private function getCustomFaviconReference(): ?string
    {
        return $this->sanitizeFaviconReference(theme_get_option('favicon', ''));
    }

    private function getBrandingIconReference(): ?string
    {
        return $this->sanitizeFaviconReference(get_env_value('BRANDING_ICON', ''));
    }

    private function sanitizeFaviconReference(mixed $value): ?string
    {
        $reference = trim((string) $value);
        if ($reference === '' || $reference === '0') {
            return null;
        }

        if (str_starts_with($reference, '/')) {
            return $this->isAllowedImageReference($reference) ? $reference : null;
        }

        if (! $this->isAbsoluteHttpUrl($reference)) {
            return null;
        }

        return $this->isAllowedImageReference($reference) ? $reference : null;
    }

    private function isAllowedImageReference(string $reference): bool
    {
        $path = parse_url($reference, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    private function isAbsoluteHttpUrl(string $value): bool
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function resolveLocalPathFromReference(string $reference): ?string
    {
        if (str_starts_with($reference, '/')) {
            $candidate = public_path(ltrim($reference, '/'));

            return is_file($candidate) ? $candidate : null;
        }

        if (! $this->isAbsoluteHttpUrl($reference)) {
            return null;
        }

        $path = parse_url($reference, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $requestHost = strtolower((string) request()->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $referenceHost = strtolower((string) parse_url($reference, PHP_URL_HOST));

        if (! in_array($referenceHost, array_filter([$requestHost, $appHost]), true)) {
            return null;
        }

        $candidate = public_path(ltrim($path, '/'));

        return is_file($candidate) ? $candidate : null;
    }

    private function resolveThemeFallbackIconPath(): ?string
    {
        $activeTheme = Theme::getActiveTheme();
        if (! $activeTheme) {
            return null;
        }

        $hierarchy = Theme::getThemeHierarchy($activeTheme['directory']);
        foreach ($hierarchy as $themeDirectory) {
            $candidate = Theme::getThemesPath().'/'.$themeDirectory.'/assets/img/favicon.png';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectMimeType(?string $path, string $bytes): string
    {
        $mime = '';

        if ($path !== null && $path !== '' && is_file($path)) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                $mime = strtolower(trim($detected));
            }
        }

        if ($mime === '') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== '') {
                    $mime = strtolower(trim($detected));
                }
            }
        }

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function resolveThemeColor(): string
    {
        $color = trim((string) theme_get_option('primary_color', setting('branding_primary_color', '#252525')));

        return $this->sanitizeHexColor($color, '#252525');
    }

    private function resolveBackgroundColor(): string
    {
        $color = trim((string) setting('branding_secondary_color', '#ffffff'));

        return $this->sanitizeHexColor($color, '#ffffff');
    }

    private function sanitizeHexColor(string $color, string $fallback): string
    {
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color) === 1) {
            return strtolower($color);
        }

        return $fallback;
    }

    private function resolveSiteTitle(): string
    {
        $title = trim((string) setting('site_title', config('app.name', 'Astero')));

        return $title !== '' ? $title : 'Astero';
    }

    private function buildManifestJson(string $version): string
    {
        $siteTitle = $this->resolveSiteTitle();
        $shortName = Str::limit($siteTitle, 20, '');

        $manifest = [
            'name' => $siteTitle,
            'short_name' => $shortName !== '' ? $shortName : 'Site',
            'icons' => [
                [
                    'src' => $this->buildFrontendAssetUrl('/web-app-manifest-192x192.png', $version),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => $this->buildFrontendAssetUrl('/web-app-manifest-512x512.png', $version),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
            'theme_color' => $this->resolveThemeColor(),
            'background_color' => $this->resolveBackgroundColor(),
            'display' => 'standalone',
            'start_url' => '/',
            'scope' => '/',
        ];

        return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function buildFrontendAssetUrl(string $path, string $version): string
    {
        $assetPath = '/'.ltrim($path, '/');
        $parts = parse_url($assetPath);

        if ($parts === false) {
            $separator = str_contains($assetPath, '?') ? '&' : '?';

            return $assetPath.$separator.'source=theme&v='.$version;
        }

        $queryParams = [];

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $queryParams);
        }

        $queryParams['source'] = 'theme';
        $queryParams['v'] = $version;

        $parts['query'] = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $rebuilt = (string) ($parts['path'] ?? $assetPath);

        if ($parts['query'] !== '') {
            $rebuilt .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
