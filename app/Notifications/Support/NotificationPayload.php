<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;

final class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private array $payload
    ) {}

    public static function make(string $title, string $text): self
    {
        return new self([
            'title' => $title,
            'text' => $text,
            'links' => [],
        ]);
    }

    public function module(string $module): self
    {
        return $this->with('module', $module);
    }

    public function type(string $type): self
    {
        return $this->with('type', $type);
    }

    public function category(NotificationCategory|string $category): self
    {
        return $this->with(
            'category',
            $category instanceof NotificationCategory ? $category->value : $category,
        );
    }

    public function priority(NotificationPriority|string $priority): self
    {
        return $this->with(
            'priority',
            $priority instanceof NotificationPriority ? $priority->value : $priority,
        );
    }

    public function icon(string $icon): self
    {
        return $this->with('icon', $icon);
    }

    public function backendLink(?string $href, string $label = 'Open in app'): self
    {
        $next = $this->with('url_backend', $href);

        if ($next->payload['url'] ?? null) {
            return $next->link($label, $href, false);
        }

        return $next
            ->with('url', $href)
            ->link($label, $href, false);
    }

    public function frontendLink(?string $href, string $label = 'Open external page'): self
    {
        return $this
            ->with('url_frontend', $href)
            ->link($label, $href, true);
    }

    /**
     * @param  array<int, array{label?: string, href?: string, url?: string, external?: bool}>  $links
     */
    public function links(array $links): self
    {
        $next = $this;

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $next = $next->link(
                (string) ($link['label'] ?? 'Open link'),
                (string) ($link['href'] ?? $link['url'] ?? ''),
                isset($link['external']) ? (bool) $link['external'] : null,
            );
        }

        return $next;
    }

    public function link(string $label, ?string $href, ?bool $external = null): self
    {
        $normalizedHref = trim((string) $href);

        if ($normalizedHref === '') {
            return $this;
        }

        $normalizedLabel = trim($label);

        if ($normalizedLabel === '') {
            $normalizedLabel = 'Open link';
        }

        $links = $this->payload['links'] ?? [];
        $links[] = [
            'label' => $normalizedLabel,
            'href' => $normalizedHref,
            'external' => $external ?? preg_match('/^https?:\/\//i', $normalizedHref) === 1,
        ];

        return $this->with('links', $links);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function extra(array $extra): self
    {
        $payload = $this->payload;

        foreach ($extra as $key => $value) {
            $payload[$key] = $value;
        }

        return new self($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (($this->payload['links'] ?? []) === []) {
            unset($this->payload['links']);
        }

        return $this->payload;
    }

    private function with(string $key, mixed $value): self
    {
        $payload = $this->payload;

        if ($value === null || $value === '') {
            unset($payload[$key]);

            return new self($payload);
        }

        $payload[$key] = $value;

        return new self($payload);
    }
}
