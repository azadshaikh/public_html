<?php

namespace Modules\CMS\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Modules\CMS\Models\DesignBlock;
use Modules\CMS\Models\Form;

class SectionsService
{
    public function getAllDesignblocks(): array
    {
        $response_data = [
            'blocks' => [],
            'sections' => [],
            'components' => [],
        ];

        /** @var EloquentCollection<int, DesignBlock> $designblocks */
        $designblocks = DesignBlock::query()->where('status', 'published')->orderBy('title', 'asc')->get();

        if ($designblocks->count() > 0) {
            foreach ($designblocks as $designblock) {
                $plural_type = Str::plural($designblock->design_type);
                $uniqueKey = $designblock->uuid ?: $designblock->id;

                $html = $designblock->html;
                if ($plural_type === 'sections') {
                    $html = $this->normalizeSectionHtml(
                        $html,
                        title: $designblock->title,
                        categoryId: $designblock->category_id,
                        uniqueKey: (string) $uniqueKey
                    );
                }

                $previewImage = $designblock->getPreviewImageUrl();

                $item = [
                    'id' => $plural_type.'-'.$designblock->category_id.'/'.$uniqueKey,
                    'sortcode' => (string) $uniqueKey,
                    'name' => $designblock->title,
                    // Alias for older/alternate consumers that expect "title".
                    'title' => $designblock->title,
                    'html' => $html,
                    // For builder insertion, we currently only use HTML.
                    'css' => '',
                    'js' => '',
                ];

                // Only send image when a preview image exists.
                // The builder handles missing previews more gracefully than a generic fallback.
                if (! empty($previewImage)) {
                    $item['image'] = $previewImage;
                }

                $response_data[$plural_type][$designblock->category_name][] = $item;
            }
        }

        $forms = $this->getForms();
        if ($forms !== []) {
            $response_data['blocks']['cms-forms'] = $forms;
        }

        return $response_data;
    }

    public function getForms(): array
    {
        $response_data = [];
        /** @var EloquentCollection<int, Form> $forms */
        $forms = Form::published()->get();
        if ($forms->count() > 0) {
            foreach ($forms as $form) {
                $response_data[] = [
                    'id' => 'cms-forms/'.$form->shortcode,
                    'shortcode' => $form->shortcode,
                    'name' => $form->title,
                    'title' => $form->title,
                    'image' => empty($form->featured_image_url) ? asset('assets/images/cms-form.png') : $form->featured_image_url,
                    'html' => $form->html,
                ];
            }
        }

        return $response_data;
    }

    protected function normalizeSectionHtml(?string $html, string $title, ?string $categoryId, string $uniqueKey): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // Built-in sections commonly look like:
        // <section class="posts-1" title="latest-post-1"> ... </section>
        // We'll emulate this for DB-backed sections.
        $baseClass = Str::slug($categoryId ?: 'section') ?: 'section';
        $sectionClass = $baseClass.'-'.Str::slug($uniqueKey);
        $titleSlug = Str::slug($title) ?: $sectionClass;
        $safeTitleSlug = e($titleSlug);
        $safeLabel = e($title);

        // Built-in sections always have a section-like root element.
        // The builder section navigator relies on section-ish elements and their `title` attribute.
        if (preg_match('/^<(section|header|footer|main|nav)\b/i', $html) === 1) {
            return (string) preg_replace_callback(
                '/^<(section|header|footer|main|nav)\b([^>]*)>/i',
                function (array $matches) use ($sectionClass, $safeTitleSlug, $safeLabel): string {
                    $tag = $matches[1];
                    $attrs = $matches[2];

                    $hasTitle = preg_match('/\btitle\s*=\s*["\"]/i', $attrs) === 1;
                    $hasDataSection = preg_match('/\bdata-section\s*=\s*["\"]/i', $attrs) === 1;

                    // Ensure class contains our marker class (append safely if class exists).
                    if (preg_match('/\bclass\s*=\s*(["\"])(.*?)\1/i', $attrs, $classMatch) === 1) {
                        $quote = $classMatch[1];
                        $existing = $classMatch[2];
                        $classes = preg_split('/\s+/', trim($existing)) ?: [];
                        if (! in_array($sectionClass, $classes, true)) {
                            $classes[] = $sectionClass;
                        }

                        $newClassValue = implode(' ', array_filter($classes));
                        $attrs = (string) preg_replace(
                            '/\bclass\s*=\s*(["\"])(.*?)\1/i',
                            'class='.$quote.e($newClassValue).$quote,
                            $attrs,
                            1
                        );
                    } else {
                        $attrs .= ' class="'.e($sectionClass).'"';
                    }

                    if (! $hasTitle) {
                        $attrs .= ' title="'.$safeTitleSlug.'"';
                    }

                    if (! $hasDataSection) {
                        $attrs .= ' data-section="'.$safeLabel.'"';
                    }

                    return '<'.$tag.$attrs.'>';
                },
                $html,
                1
            );
        }

        return '<section class="'.e($sectionClass)."\" title=\"{$safeTitleSlug}\" data-section=\"{$safeLabel}\">\n{$html}\n</section>";
    }
}
