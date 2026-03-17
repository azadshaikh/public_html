# Laravel Media Library Reference

Complete reference for `spatie/laravel-medialibrary`. Full documentation: https://spatie.be/docs/laravel-medialibrary

## Model Setup

Implement `HasMedia` and use `InteractsWithMedia`:

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BlogPost extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb');
    }
}
```

## Adding Media

### From uploaded file

```php
$model->addMedia($request->file('image'))->toMediaCollection('images');
```

### From request (shorthand)

```php
$model->addMediaFromRequest('image')->toMediaCollection('images');
```

### From URL

```php
$model->addMediaFromUrl('https://example.com/image.jpg')->toMediaCollection('images');
```

### From string content

```php
$model->addMediaFromString('raw content')->usingFileName('file.txt')->toMediaCollection('files');
```

### From base64

```php
$model->addMediaFromBase64($base64Data)->usingFileName('photo.jpg')->toMediaCollection('images');
```

### From stream

```php
$model->addMediaFromStream($stream)->usingFileName('file.pdf')->toMediaCollection('files');
```

### From existing disk

```php
$model->addMediaFromDisk('path/to/file.jpg', 's3')->toMediaCollection('images');
```

### Multiple files from request

```php
$model->addMultipleMediaFromRequest(['images'])->each(function ($fileAdder) {
    $fileAdder->toMediaCollection('images');
});

$model->addAllMediaFromRequest()->each(function ($fileAdder) {
    $fileAdder->toMediaCollection('images');
});
```

### Copy instead of move

```php
$model->copyMedia($pathToFile)->toMediaCollection('images');
// or
$model->addMedia($pathToFile)->preservingOriginal()->toMediaCollection('images');
```

## FileAdder Options

All methods are chainable before calling `toMediaCollection()`:

```php
$model->addMedia($file)
    ->usingName('Custom Name')
    ->usingFileName('custom-name.jpg')
    ->setOrder(3)
    ->withCustomProperties(['alt' => 'A landscape photo'])
    ->withManipulations(['thumb' => ['filter' => 'greyscale']])
    ->withResponsiveImages()
    ->storingConversionsOnDisk('s3')
    ->addCustomHeaders(['CacheControl' => 'max-age=31536000'])
    ->toMediaCollection('images');
```

### Store on cloud disk

```php
$model->addMedia($file)->toMediaCollectionOnCloudDisk('images');
```

## Media Collections

Define in `registerMediaCollections()`:

```php
public function registerMediaCollections(): void
{
    // Basic collection
    $this->addMediaCollection('images');

    // Single file (replacing previous on new upload)
    $this->addMediaCollection('avatar')
        ->singleFile();

    // Keep only latest N items
    $this->addMediaCollection('recent_photos')
        ->onlyKeepLatest(5);

    // Specific disk
    $this->addMediaCollection('downloads')
        ->useDisk('s3');

    // With conversions disk
    $this->addMediaCollection('photos')
        ->useDisk('s3')
        ->storeConversionsOnDisk('s3-thumbnails');

    // MIME type restriction
    $this->addMediaCollection('documents')
        ->acceptsMimeTypes(['application/pdf', 'application/zip']);

    // Custom validation
    $this->addMediaCollection('images')
        ->acceptsFile(function ($file) {
            return true;
        });

    // Fallback URL/path when collection is empty
    $this->addMediaCollection('avatar')
        ->singleFile()
        ->useFallbackUrl('/images/default-avatar.jpg')
        ->useFallbackPath(public_path('/images/default-avatar.jpg'));

    // Enable responsive images for entire collection
    $this->addMediaCollection('hero_images')
        ->withResponsiveImages();

    // Collection-specific conversions
    $this->addMediaCollection('photos')
        ->registerMediaConversions(function () {
            // collection-specific conversions
        });
}
```

## Media Conversions

Define in `registerMediaConversions()`:

```php
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')
        ->fit(Fit::Contain, 300, 300)
        ->nonQueued();

    $this->addMediaConversion('preview')
        ->fit(Fit::Crop, 500, 500)
        ->queued();

    $this->addMediaConversion('banner')
        ->fit(Fit::Max, 1200, 630)
        ->format('webp')
        ->quality(85)
        ->sharpen(10);

    // Conditional conversion based on media properties
    if ($media?->mime_type === 'image/png') {
        $this->addMediaConversion('png-thumb');
    }
}
```

### Image Manipulation Methods (via spatie/image)

Resizing and fitting:
- `width(int)`, `height(int)` — constrain dimensions
- `fit(Fit, int, int)` — fit within bounds using `Fit::Contain`, `Fit::Max`, `Fit::Fill`, `Fit::Stretch`, `Fit::Crop`
- `crop(int, int)` — crop to exact dimensions

Effects:
- `sharpen(int)`, `blur(int)`, `pixelate(int)`
- `greyscale()`, `sepia()`
- `brightness(int)`, `contrast(int)`, `colorize(int, int, int)`

Orientation:
- `orientation(int)`, `flip(string)`, `rotate(int)`

Format:
- `format(string)` — `'jpg'`, `'png'`, `'webp'`, `'avif'`
- `quality(int)` — 1-100

Other:
- `border(int, string, string)`, `watermark(string)`
- `optimize()`, `nonOptimized()`

### Conversion Configuration

- `performOnCollections('col1', 'col2')` — limit to specific collections
- `queued()` / `nonQueued()` — run async or sync
- `withResponsiveImages()` — also generate responsive variants for this conversion
- `keepOriginalImageFormat()` — preserve png/webp/gif instead of converting to jpg
- `pdfPageNumber(int)` — which PDF page to render
- `extractVideoFrameAtSecond(int)` — video thumbnail timing

## Retrieving Media

### Getting media items

```php
$media = $model->getMedia('images');
$first = $model->getFirstMedia('images');
$last  = $model->getLastMedia('images');
$has   = $model->hasMedia('images');
```

### Getting URLs

```php
$url      = $model->getFirstMediaUrl('images');
$thumbUrl = $model->getFirstMediaUrl('images', 'thumb');
$lastUrl  = $model->getLastMediaUrl('images', 'thumb');
```

### Getting paths

```php
$path      = $model->getFirstMediaPath('images');
$thumbPath = $model->getFirstMediaPath('images', 'thumb');
```

### Temporary URLs (S3)

```php
$tempUrl = $model->getFirstTemporaryUrl(
    now()->addMinutes(30),
    'images',
    'thumb'
);
```

### Fallback URLs

```php
$url = $model->getFallbackMediaUrl('avatar');
```

### From the Media model

```php
$media = $model->getFirstMedia('images');

$media->getUrl();
$media->getUrl('thumb');
$media->getPath();
$media->getFullUrl();
$media->getTemporaryUrl(now()->addMinutes(30));
$media->hasGeneratedConversion('thumb');
```

### Filtering media

```php
$media = $model->getMedia('images', function (Media $media) {
    return $media->getCustomProperty('featured') === true;
});

$media = $model->getMedia('images', ['mime_type' => 'image/jpeg']);
```

## Custom Properties

Store arbitrary metadata on media items:

```php
// When adding
$model->addMedia($file)
    ->withCustomProperties([
        'alt' => 'Updated text',
        'featured' => true,
    ])
    ->toMediaCollection('images');

// Get/set on existing media
$media->setCustomProperty('alt', 'Updated text');
$media->save();

$alt = $media->getCustomProperty('alt');
$has = $media->hasCustomProperty('alt');
$media->forgetCustomProperty('alt');
$media->save();
```

## Responsive Images

Generate multiple sizes for optimal loading:

```php
// On the FileAdder
$model->addMedia($file)
    ->withResponsiveImages()
    ->toMediaCollection('images');

// On a conversion
$this->addMediaConversion('hero')
    ->fit(Fit::Max, 1200, 800)
    ->withResponsiveImages();

// On a collection
$this->addMediaCollection('photos')
    ->withResponsiveImages();
```

### Using in Blade

```blade
{{-- Renders img tag with srcset --}}
{{ $media->toHtml() }}

{{-- With attributes --}}
{{ $media->img()->attributes(['class' => 'w-full', 'alt' => 'Photo']) }}

{{-- Get srcset string --}}
<img src="{{ $media->getUrl() }}" srcset="{{ $media->getSrcset() }}" />

{{-- Responsive conversion --}}
<img src="{{ $media->getUrl('hero') }}" srcset="{{ $media->getSrcset('hero') }}" />
```

### Placeholder SVG

```php
$svg = $media->responsiveImages()->getPlaceholderSvg();
```

## Managing Media

### Clear a collection

```php
$model->clearMediaCollection('images');
```

### Clear except specific items

```php
$model->clearMediaCollectionExcept('images', $mediaToKeep);
```

### Delete specific media

```php
$model->deleteMedia($mediaId);
```

### Delete all media

```php
$model->deleteAllMedia();
```

### Delete model but keep media files

```php
$model->deletePreservingMedia();
```

### Reorder media

```php
Media::setNewOrder([3, 1, 2]); // media IDs in desired order
```

### Move/copy media between models

```php
$media->move($otherModel, 'images');
$media->copy($otherModel, 'images');
```

## Events

```php
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionWillStartEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
```

Listen to these events to hook into the media lifecycle:

```php
Event::listen(MediaHasBeenAddedEvent::class, function ($event) {
    $event->media; // the added Media model
});

Event::listen(ConversionHasBeenCompletedEvent::class, function ($event) {
    $event->media;
    $event->conversion;
});
```

## Configuration

Key `config/media-library.php` options:

```php
return [
    'disk_name' => 'public',
    'default_loading_attribute_value' => null,
];
```

### Custom Path Generator

```php
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return 'custom/path/';
    }

    public function getPathForConversions(Media $media): string
    {
        return 'custom/path/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return 'custom/path/responsive/';
    }
}
```

### Custom File Namer

```php
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

class CustomFileNamer extends FileNamer
{
    public function originalFileName(string $fileName): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        return $this->originalFileName($fileName) . '-' . $conversion->getName();
    }

    public function responsiveFileName(string $fileName): string
    {
        return $this->originalFileName($fileName);
    }
}
```

### Custom Media Model

```php
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    // Add custom methods, scopes, or override behavior
}
```

Register in config: `'media_model' => App\Models\Media::class`

## Downloading Media

### Single file

```php
return $media->toResponse($request); // download
return $media->toInlineResponse($request); // display inline
return $media->stream(); // stream
```

### ZIP download of collection

```php
use Spatie\MediaLibrary\Support\MediaStream;

return MediaStream::create('photos.zip')
    ->addMedia($model->getMedia('images'));
```

## Using with API Resources

```php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->getFirstMediaUrl('images'),
            'thumb_url' => $this->getFirstMediaUrl('images', 'thumb'),
        ];
    }
}
```
