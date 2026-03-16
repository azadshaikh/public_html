<?php

namespace Modules\CMS\Services\Components;

use Modules\CMS\Models\Menu;

/**
 * Menu Component
 * Renders navigation menus
 * Usage: {menu location='primary' class='nav navbar-nav' id='main-menu'}
 */
class MenuComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        $location = $this->param($params, 'location', 'primary');
        $class = $this->param($params, 'class', 'nav navbar-nav');
        $id = $this->param($params, 'id', 'menu-'.$location);
        $container = $this->param($params, 'container', 'ul');

        $menu = Menu::getByLocation($location);

        if (! $menu || ! $menu->hasItems()) {
            return '';
        }

        // Build container attributes
        $containerAttributes = [
            'id' => $id,
            'class' => $class,
        ];

        // Add any additional attributes
        foreach ($params as $key => $value) {
            if (! in_array($key, ['location', 'container'])) {
                $containerAttributes[$key] = $value;
            }
        }

        $html = '<'.$container.' '.$this->buildAttributes($containerAttributes).'>';

        foreach ($menu->getCachedItems() as $item) {
            $html .= $this->renderMenuItem($item, 0);
        }

        return $html.('</'.$container.'>');
    }

    /**
     * Render a single menu item with its children
     */
    protected function renderMenuItem(Menu $item, int $depth = 0): string
    {
        $hasChildren = $item->children->count() > 0;
        $cssClasses = trim((string) $item->css_classes);
        $target = $item->target ?: '_self';
        $url = $item->url;
        $title = $this->escape($item->title);
        $linkTitle = $item->link_title ? $this->escape($item->link_title) : null;
        $linkRel = $item->link_rel ? trim((string) $item->link_rel) : null;
        $icon = $item->icon ? trim((string) $item->icon) : null;

        // Determine if this is a dropdown item (depth > 0)
        $isDropdownItem = $depth > 0;

        // Build item classes
        if ($isDropdownItem) {
            // For dropdown items, we don't need nav-item class
            $itemClasses = [];
            if ($cssClasses !== '' && $cssClasses !== '0') {
                $itemClasses[] = $cssClasses;
            }
        } else {
            // For top-level items, use nav-item
            $itemClasses = ['nav-item'];
            if ($cssClasses !== '' && $cssClasses !== '0') {
                $itemClasses[] = $cssClasses;
            }

            if ($hasChildren) {
                $itemClasses[] = 'dropdown';
            }
        }

        // Check if current page matches menu item URL
        $isCurrent = $this->isCurrentUrl($url);
        if ($isCurrent) {
            $itemClasses[] = 'active';
        }

        $html = '<li';
        if ($itemClasses !== []) {
            $html .= ' class="'.implode(' ', $itemClasses).'"';
        }

        $html .= '>';

        // Build link classes
        $linkClasses = [];
        if ($isDropdownItem) {
            $linkClasses[] = 'dropdown-item';
        } else {
            $linkClasses[] = 'nav-link';
            if ($hasChildren) {
                $linkClasses[] = 'dropdown-toggle';
            }
        }

        // Build link attributes
        $linkAttributes = [
            'href' => $url,
            'target' => $target,
        ];

        $linkAttributes['class'] = implode(' ', $linkClasses);
        $linkAttributes['title'] = $linkTitle;

        if ($linkRel) {
            $linkAttributes['rel'] = $linkRel;
        }

        if ($hasChildren && $depth === 0) {
            $linkAttributes['data-bs-toggle'] = 'dropdown';
            $linkAttributes['aria-expanded'] = 'false';
            $linkAttributes['role'] = 'button';
        }

        if ($isCurrent) {
            $linkAttributes['aria-current'] = 'page';
        }

        $html .= '<a '.$this->buildAttributes($linkAttributes).'>';

        // Add icon if provided
        if ($icon) {
            $html .= '<i class="'.$this->escape($icon).'" aria-hidden="true"></i> ';
        }

        $html .= $title;

        if ($hasChildren) {
            $html .= ' <span class="dropdown-indicator"></span>';
        }

        $html .= '</a>';

        // Render children
        if ($hasChildren) {
            $childrenClass = $depth === 0 ? 'dropdown-menu' : 'dropdown-submenu';
            $html .= '<ul class="'.$childrenClass.'">';
            foreach ($item->children as $child) {
                $html .= $this->renderMenuItem($child, $depth + 1);
            }

            $html .= '</ul>';
        }

        return $html.'</li>';
    }

    /**
     * Check if the given URL is the current page
     */
    protected function isCurrentUrl(string $url): bool
    {
        $currentUrl = url()->current();
        $compareUrl = url($url);

        return $currentUrl === $compareUrl || rtrim($currentUrl, '/') === rtrim($compareUrl, '/');
    }
}
