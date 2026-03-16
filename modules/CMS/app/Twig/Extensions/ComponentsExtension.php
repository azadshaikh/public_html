<?php

namespace Modules\CMS\Twig\Extensions;

use Modules\CMS\Services\Components\AdminBarComponent;
use Modules\CMS\Services\Components\FormResponseComponent;
use Modules\CMS\Services\Components\MenuComponent;
use Modules\CMS\Services\Components\ResponsiveImageComponent;
use Modules\CMS\Services\Components\SeoMetaComponent;
use Modules\CMS\Services\Components\WidgetComponent;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Component functions for Twig templates
 */
class ComponentsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('responsive_image', $this->responsiveImage(...), ['is_safe' => ['html']]),
            new TwigFunction('form_response', $this->formResponse(...), ['is_safe' => ['html']]),
            new TwigFunction('seo_meta', $this->seoMeta(...), ['is_safe' => ['html']]),
            new TwigFunction('menu', $this->menu(...), ['is_safe' => ['html']]),
            new TwigFunction('widget', $this->widget(...), ['is_safe' => ['html']]),
            // integrations() removed - auto-injected by SeoMetaComponent (head) and ThemeService (footer)
            new TwigFunction('admin_bar', $this->adminBar(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render responsive image
     * Usage: {{ responsive_image(media_id, {'class': 'img-fluid', 'alt': 'Image'}) }}
     */
    public function responsiveImage(?int $mediaId = null, array $options = []): string
    {
        if (! $mediaId) {
            return '';
        }

        $component = new ResponsiveImageComponent;

        $params = array_merge(['media_id' => $mediaId], $options);

        return $component->render($params);
    }

    /**
     * Render form response (success/error messages)
     * Usage: {{ form_response() }}
     */
    public function formResponse(): string
    {
        $component = new FormResponseComponent;

        return $component->render([]);
    }

    /**
     * Render SEO meta tags
     * Usage: {{ seo_meta() }}
     */
    public function seoMeta(): string
    {
        $component = new SeoMetaComponent;

        return $component->render([]);
    }

    /**
     * Render menu
     * Usage: {{ menu('primary', {'class': 'nav'}) }}
     */
    public function menu(string $location, array $options = []): string
    {
        if ($location === '' || $location === '0') {
            return '';
        }

        $component = new MenuComponent;

        $params = array_merge(['location' => $location], $options);

        return $component->render($params);
    }

    /**
     * Render widget
     * Usage: {{ widget('text', {'content': 'Hello'}) }}
     */
    public function widget(string $type, array $options = []): string
    {
        if ($type === '' || $type === '0') {
            return '';
        }

        $component = new WidgetComponent;

        $params = array_merge(['type' => $type], $options);

        return $component->render($params);
    }

    // integrations() method removed - auto-injected by SeoMetaComponent (head) and ThemeService (footer)

    /**
     * Render admin bar
     * Usage: {{ admin_bar() }}
     */
    public function adminBar(): string
    {
        $component = new AdminBarComponent;

        return $component->render([]);
    }
}
