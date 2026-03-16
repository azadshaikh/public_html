@php
    $level = $level ?? 0;
    $typeIcons = [
        'home' => 'ri-home-line',
        'page' => 'ri-file-text-line',
        'custom' => 'ri-link',
        'category' => 'ri-folder-line',
        'tag' => 'ri-price-tag-3-line',
        'archive' => 'ri-archive-line',
        'search' => 'ri-search-line',
    ];
    $typeIcon = $typeIcons[$item->type] ?? 'ri-link';
    $typeBadgeClass = match ($item->type) {
        'home' => 'bg-success-subtle text-success',
        'page' => 'bg-primary-subtle text-primary',
        'category' => 'bg-info-subtle text-info',
        'tag' => 'bg-warning-subtle text-warning',
        'archive' => 'bg-secondary-subtle text-secondary',
        default => 'bg-light text-dark',
    };
@endphp
<div class="menu-item {{ !$item->is_active ? 'menu-item-inactive' : '' }}"
    data-id="{{ $item->id }}"
    data-parent-id="{{ $item->parent_id ?: '' }}"
    data-type="{{ $item->type }}"
    data-is-active="{{ $item->is_active ? '1' : '0' }}"
    data-target="{{ $item->target }}"
    data-css-classes="{{ $item->css_classes }}"
    data-description="{{ $item->description }}"
    data-object-id="{{ $item->object_id }}"
    data-link-title="{{ $item->link_title }}"
    data-link-rel="{{ $item->link_rel }}"
    data-icon="{{ $item->icon }}"
    data-url="{{ $item->url ?: '' }}"
    draggable="true">
    <div class="menu-item-content">
        {{-- Drag Handle (desktop: drag, mobile: opens reorder modal) --}}
        <div class="menu-item-drag-handle" title="Drag to reorder" data-id="{{ $item->id }}">
            <i class="ri-draggable"></i>
        </div>

        @if($item->icon)
        <div class="menu-item-icon">
            <i class="{{ $item->icon }}"></i>
        </div>
        @endif

        <div class="menu-item-info flex-grow-1">
            <div class="menu-item-main-line">
                <span class="menu-item-title">{{ $item->title }}</span>
                <div class="menu-item-badges d-none d-md-flex">
                    <span class="badge {{ $typeBadgeClass }} menu-item-type">
                        {{ ucfirst($item->type ?? 'custom') }}
                    </span>
                    @if (($item->target ?? '_self') === '_blank')
                        <span class="badge bg-info-subtle text-info menu-badge-external" title="Opens in new tab">
                            <i class="ri-external-link-line"></i>
                        </span>
                    @endif
                    @if (!$item->is_active)
                        <span class="badge bg-warning-subtle text-warning menu-badge-hidden">
                            <i class="ri-eye-off-line me-1"></i>Hidden
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="menu-item-actions">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-light-primary edit-item"
                    data-id="{{ $item->id }}" title="Edit">
                    <i class="ri-pencil-line"></i>
                </button>
                <button type="button" class="btn btn-light-danger delete-item"
                    data-id="{{ $item->id }}" title="Delete">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="menu-children" data-level="{{ $level + 1 }}">
        @if ($item->allChildren && $item->allChildren->count() > 0)
            @foreach ($item->allChildren->sortBy('sort_order') as $child)
                @include('cms::menus.partials.menu-item', ['item' => $child, 'level' => $level + 1])
            @endforeach
        @endif
    </div>
</div>
