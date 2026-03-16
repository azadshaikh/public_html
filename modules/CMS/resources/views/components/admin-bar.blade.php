@if (($can_show_admin_bar ?? false) && ! request()->has('customizer_preview') && ! request()->has('editor_preview') && ! request()->routeIs('cms.builder.*'))
    @php
        $isCollapsed = isset($_COOKIE['adminbar-visible']) && $_COOKIE['adminbar-visible'] == 0;
        $editLink = null;

        if (isset($edit_link) && is_array($edit_link)) {
            $editLink = $edit_link;
        } elseif (isset($edit_url) && is_string($edit_url) && $edit_url !== '') {
            $matches = [];
            if (preg_match('/href="([^"]+)"/', $edit_url, $matches) !== 1) {
                preg_match("/href='([^']+)'/", $edit_url, $matches);
            }
            if (! empty($matches[1])) {
                $label = trim(strip_tags($edit_url));
                $editLink = [
                    'url' => $matches[1],
                    'label' => $label !== '' ? $label : 'Edit',
                ];
            }
        }

        // Preview banner configuration
        $showPreviewBanner = isset($preview_page) && $preview_page;
        $previewStatusLabel = '';
        $previewStatusClass = '';
        $previewContentType = '';
        $previewEditUrl = null;

        if ($showPreviewBanner) {
            $previewStatusLabel = match($preview_page->status) {
                'draft' => 'Draft',
                'pending' => 'Pending Review',
                'scheduled' => 'Scheduled',
                'private' => 'Private',
                default => ucfirst($preview_page->status),
            };
            $previewStatusClass = match($preview_page->status) {
                'draft' => 'warning',
                'pending' => 'info',
                'scheduled' => 'primary',
                'private' => 'secondary',
                default => 'warning',
            };
            $previewContentType = ucfirst($preview_page->type);

            // Build edit URL for preview page
            $previewEditUrl = match($preview_page->type) {
                'page' => route('cms.pages.edit', $preview_page->id),
                'post' => route('cms.posts.edit', $preview_page->id),
                'category' => route('cms.categories.edit', $preview_page->id),
                'tag' => route('cms.tags.edit', $preview_page->id),
                default => null,
            };
        }
    @endphp

    {{-- Preview Banner --}}
    @if($showPreviewBanner)
    <style>
        #preview_banner {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 12px !important;
            height: 36px !important;
            padding: 0 20px !important;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #1a1a1a !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            z-index: 100000 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            -webkit-font-smoothing: antialiased !important;
        }

        #preview_banner .preview-icon {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 20px !important;
            height: 20px !important;
        }

        #preview_banner .preview-status-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 3px 8px !important;
            background: rgba(0, 0, 0, 0.15) !important;
            border-radius: 4px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        #preview_banner .preview-message {
            flex: 0 1 auto !important;
        }

        #preview_banner .preview-edit-link {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 5px 12px !important;
            background: rgba(0, 0, 0, 0.2) !important;
            border-radius: 4px !important;
            color: #1a1a1a !important;
            text-decoration: none !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            transition: background 0.2s ease !important;
        }

        #preview_banner .preview-edit-link:hover {
            background: rgba(0, 0, 0, 0.3) !important;
            color: #1a1a1a !important;
        }

        @media (max-width: 640px) {
            #preview_banner {
                font-size: 11px !important;
                gap: 8px !important;
                padding: 0 12px !important;
            }

            #preview_banner .preview-message {
                display: none !important;
            }
        }
    </style>

    <div id="preview_banner">
        <span class="preview-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        </span>
        <span class="preview-status-badge">{{ $previewStatusLabel }}</span>
        <span class="preview-message">
            This {{ strtolower($previewContentType) }} is not published — only you can see this preview.
        </span>
        @if($previewEditUrl)
        <a href="{{ $previewEditUrl }}" class="preview-edit-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit
        </a>
        @endif
    </div>
    @endif


    <style>
        /* Admin Bar - Modern Compact Design */
        :root {
            --ab-height: 36px;
            --ab-bg: linear-gradient(180deg, #1e1e1e 0%, #151515 100%);
            --ab-bg-solid: #1a1a1a;
            --ab-border: rgba(255, 255, 255, 0.08);
            --ab-text: #e0e0e0;
            --ab-text-muted: #9ca3af;
            --ab-hover: rgba(255, 255, 255, 0.08);
            --ab-active: rgba(255, 255, 255, 0.12);
            --ab-accent: #3b82f6;
            --ab-radius: 6px;
            --ab-transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --ab-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
            --ab-dropdown-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        #admin_bar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            display: flex !important;
            justify-content: flex-start !important;
            align-items: stretch !important;
            height: var(--ab-height) !important;
            color: var(--ab-text) !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif !important;
            font-size: 13px !important;
            font-weight: 450 !important;
            line-height: 1 !important;
            z-index: 99999 !important;
            overflow: visible !important;
            transition: width var(--ab-transition), background var(--ab-transition), border-radius var(--ab-transition) !important;
            box-sizing: border-box !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
        }

        #admin_bar * {
            box-sizing: border-box !important;
        }

        #admin_bar[data-state="collapsed"] {
            width: 42px !important;
            right: auto !important;
            background: var(--ab-bg-solid) !important;
            border-bottom-right-radius: var(--ab-radius) !important;
            box-shadow: var(--ab-shadow) !important;
        }

        #admin_bar[data-state="expanded"] {
            width: 100% !important;
            right: 0 !important;
            background: var(--ab-bg) !important;
            border-bottom-right-radius: 0 !important;
            box-shadow: var(--ab-shadow) !important;
        }

        /* Logo/Restore Button */
        .cms-admin-bar-restore-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: var(--ab-height) !important;
            padding: 0 12px !important;
            text-decoration: none !important;
            color: var(--ab-text) !important;
            cursor: pointer !important;
            background: transparent !important;
            min-width: 42px !important;
            transition: background var(--ab-transition) !important;
            border-right: 1px solid var(--ab-border) !important;
        }

        #admin_bar[data-state="collapsed"] .cms-admin-bar-restore-btn {
            width: 100% !important;
            border-bottom-right-radius: var(--ab-radius) !important;
            border-right: none !important;
        }

        #admin_bar[data-state="expanded"] .cms-admin-bar-restore-btn {
            width: auto !important;
            border-bottom-right-radius: 0 !important;
        }

        .cms-admin-bar-restore-btn:hover {
            background: var(--ab-hover) !important;
        }

        .cms-admin-bar-restore-btn img {
            height: 18px !important;
            width: auto !important;
            display: block !important;
            opacity: 0.95 !important;
            transition: opacity var(--ab-transition), transform var(--ab-transition) !important;
        }

        .cms-admin-bar-restore-btn:hover img {
            opacity: 1 !important;
            transform: scale(1.05) !important;
        }

        /* Mobile Toggle */
        .cms-admin-bar-mobile-toggle {
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            height: var(--ab-height) !important;
            width: 40px !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            color: var(--ab-text) !important;
            cursor: pointer !important;
            transition: background var(--ab-transition) !important;
        }

        .cms-admin-bar-mobile-toggle:hover {
            background: var(--ab-hover) !important;
        }

        .cms-admin-bar-hamburger {
            display: block !important;
            width: 16px !important;
            height: 2px !important;
            background: var(--ab-text) !important;
            position: relative !important;
            border-radius: 1px !important;
            transition: background var(--ab-transition) !important;
        }

        .cms-admin-bar-hamburger-line {
            display: block !important;
            width: 16px !important;
            height: 2px !important;
            background: var(--ab-text) !important;
            position: absolute !important;
            left: 0 !important;
            border-radius: 1px !important;
            transition: transform var(--ab-transition), top var(--ab-transition) !important;
        }

        .cms-admin-bar-hamburger-line:first-child {
            top: -5px !important;
        }

        .cms-admin-bar-hamburger-line:last-child {
            top: 5px !important;
        }

        /* Menu Container */
        .cms-admin-bar-menu {
            display: flex !important;
            align-items: stretch !important;
            flex: 1 !important;
            transition: opacity var(--ab-transition), transform var(--ab-transition) !important;
        }

        #admin_bar[data-state="collapsed"] .cms-admin-bar-menu {
            display: none !important;
            flex: 0 !important;
            width: 0 !important;
            opacity: 0 !important;
            pointer-events: none !important;
            overflow: hidden !important;
        }

        #admin_bar[data-state="expanded"] .cms-admin-bar-menu {
            display: flex !important;
            flex: 1 !important;
            width: auto !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            overflow: visible !important;
        }

        /* Primary & Secondary Sections */
        .cms-admin-bar-primary,
        .cms-admin-bar-secondary {
            display: flex !important;
            align-items: stretch !important;
            flex-wrap: nowrap !important;
            gap: 1px !important;
        }

        .cms-admin-bar-secondary {
            margin-left: auto !important;
            border-left: 1px solid var(--ab-border) !important;
        }

        /* Main Links & Buttons */
        .cms-admin-bar-main-link,
        .cms-admin-bar-main-button {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            height: var(--ab-height) !important;
            padding: 0 14px !important;
            color: var(--ab-text) !important;
            text-decoration: none !important;
            cursor: pointer !important;
            background: transparent !important;
            white-space: nowrap !important;
            transition: background var(--ab-transition), color var(--ab-transition) !important;
            position: relative !important;
        }

        .cms-admin-bar-main-button {
            border: none !important;
            font-size: 13px !important;
            font-weight: 450 !important;
            font-family: inherit !important;
        }

        .cms-admin-bar-main-link:hover,
        .cms-admin-bar-main-button:hover {
            background: var(--ab-hover) !important;
            color: #fff !important;
        }

        .cms-admin-bar-main-link:active,
        .cms-admin-bar-main-button:active {
            background: var(--ab-active) !important;
        }

        /* Edit Link Highlight */
        .cms-admin-bar-main-link.cms-admin-bar-edit-link {
            color: #60a5fa !important;
            font-weight: 500 !important;
        }

        .cms-admin-bar-main-link.cms-admin-bar-edit-link:hover {
            color: #93c5fd !important;
            background: rgba(59, 130, 246, 0.15) !important;
        }

        .cms-admin-bar-main-link.cms-admin-bar-edit-link svg {
            opacity: 0.8 !important;
        }

        /* Icon Styling */
        .cms-admin-bar-icon {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 16px !important;
            height: 16px !important;
            flex-shrink: 0 !important;
        }

        .cms-admin-bar-icon svg {
            width: 15px !important;
            height: 15px !important;
            stroke-width: 1.75 !important;
        }

        /* Dropdown */
        .cms-admin-bar-dropdown {
            display: flex !important;
            align-items: stretch !important;
            position: relative !important;
        }

        .cms-admin-bar-dropdown-arrow {
            display: flex !important;
            align-items: center !important;
            margin-left: 4px !important;
            opacity: 0.6 !important;
            transition: transform var(--ab-transition), opacity var(--ab-transition) !important;
        }

        .cms-admin-bar-dropdown-arrow svg {
            width: 10px !important;
            height: 10px !important;
        }

        .cms-admin-bar-dropdown:hover .cms-admin-bar-dropdown-arrow,
        .cms-admin-bar-dropdown.open .cms-admin-bar-dropdown-arrow {
            opacity: 1 !important;
        }

        .cms-admin-bar-dropdown.open .cms-admin-bar-dropdown-arrow {
            transform: rotate(180deg) !important;
        }

        /* Dropdown Panel */
        .cms-admin-bar-menu-panel {
            display: none !important;
            flex-direction: column !important;
            position: absolute !important;
            top: calc(var(--ab-height) + 4px) !important;
            left: 0 !important;
            min-width: 180px !important;
            background: #252525 !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: var(--ab-radius) !important;
            box-shadow: var(--ab-dropdown-shadow) !important;
            z-index: 1000 !important;
            overflow: hidden !important;
            opacity: 0 !important;
            transform: translateY(-4px) !important;
            transition: opacity 0.15s ease, transform 0.15s ease !important;
            padding: 4px !important;
        }

        .cms-admin-bar-menu-panel.visible {
            display: flex !important;
            opacity: 1 !important;
            transform: translateY(0) !important;
        }

        .cms-admin-bar-menu-panel[data-adminbar-align="right"] {
            right: 0 !important;
            left: auto !important;
        }

        /* Submenu Links */
        .cms-admin-bar-submenu-link {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 9px 12px !important;
            color: var(--ab-text) !important;
            text-decoration: none !important;
            cursor: pointer !important;
            background: transparent !important;
            border-radius: 4px !important;
            transition: background var(--ab-transition), color var(--ab-transition) !important;
            font-size: 13px !important;
        }

        .cms-admin-bar-submenu-link:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }

        .cms-admin-bar-submenu-link.cms-admin-bar-logout {
            color: #f87171 !important;
        }

        .cms-admin-bar-submenu-link.cms-admin-bar-logout:hover {
            background: rgba(248, 113, 113, 0.15) !important;
            color: #fca5a5 !important;
        }

        .cms-admin-bar-submenu-icon {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 16px !important;
            height: 16px !important;
            opacity: 0.7 !important;
        }

        .cms-admin-bar-submenu-icon svg {
            width: 14px !important;
            height: 14px !important;
        }

        /* Divider */
        .cms-admin-bar-divider {
            height: 1px !important;
            background: rgba(255, 255, 255, 0.08) !important;
            margin: 4px 0 !important;
        }

        /* Close Button */
        .cms-admin-bar-close-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: var(--ab-height) !important;
            width: 36px !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            border-left: 1px solid var(--ab-border) !important;
            color: var(--ab-text-muted) !important;
            cursor: pointer !important;
            transition: background var(--ab-transition), color var(--ab-transition) !important;
        }

        #admin_bar[data-state="collapsed"] .cms-admin-bar-close-btn {
            display: none !important;
        }

        .cms-admin-bar-close-btn:hover {
            background: var(--ab-hover) !important;
            color: var(--ab-text) !important;
        }

        .cms-admin-bar-close-btn svg {
            width: 16px !important;
            height: 16px !important;
        }

        /* User Name */
        .cms-admin-bar-user-name {
            max-width: 120px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .cms-admin-bar-user-avatar {
            width: 22px !important;
            height: 22px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            color: #fff !important;
            text-transform: uppercase !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
        }

        .cms-admin-bar-user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
        }

        /* Responsive styles */
        @media (max-width: 782px) {
            :root {
                --ab-height: 44px;
            }

            #admin_bar[data-state="expanded"] {
                flex-wrap: wrap !important;
                height: auto !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-mobile-toggle {
                display: flex !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-close-btn {
                position: relative !important;
                margin-left: auto !important;
                order: 3 !important;
                border-left: none !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-restore-btn {
                padding: 0 14px !important;
                order: 1 !important;
                border-right: none !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-mobile-toggle {
                order: 2 !important;
            }

            #admin_bar[data-state="expanded"][data-mobile-state="open"] {
                height: auto !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-menu {
                position: absolute !important;
                top: var(--ab-height) !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                flex-direction: column !important;
                background: var(--ab-bg-solid) !important;
                border-top: 1px solid var(--ab-border) !important;
                max-height: 0 !important;
                overflow: hidden !important;
                opacity: 0 !important;
                transition: max-height 0.3s ease, opacity 0.2s ease !important;
            }

            #admin_bar[data-state="expanded"][data-mobile-state="open"] .cms-admin-bar-menu {
                max-height: calc(100vh - var(--ab-height)) !important;
                overflow-y: auto !important;
                opacity: 1 !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-primary,
            #admin_bar[data-state="expanded"] .cms-admin-bar-secondary {
                flex-direction: column !important;
                width: 100% !important;
                gap: 0 !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-secondary {
                margin-left: 0 !important;
                margin-top: 0 !important;
                border-left: none !important;
                border-top: 1px solid var(--ab-border) !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-main-link,
            #admin_bar[data-state="expanded"] .cms-admin-bar-main-button {
                width: 100% !important;
                height: 48px !important;
                justify-content: flex-start !important;
                padding: 0 20px !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-dropdown {
                flex-direction: column !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-dropdown .cms-admin-bar-main-button {
                justify-content: space-between !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-menu-panel {
                position: static !important;
                width: 100% !important;
                background: rgba(0, 0, 0, 0.2) !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin-top: 0 !important;
                padding: 4px 8px !important;
                transform: none !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-menu-panel:not(.visible) {
                display: none !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-menu-panel.visible {
                display: flex !important;
                opacity: 1 !important;
            }

            #admin_bar[data-state="expanded"] .cms-admin-bar-submenu-link {
                padding: 12px 16px !important;
            }

            #admin_bar[data-state="expanded"][data-mobile-state="open"] .cms-admin-bar-hamburger {
                background: transparent !important;
            }

            #admin_bar[data-state="expanded"][data-mobile-state="open"] .cms-admin-bar-hamburger-line:first-child {
                top: 0 !important;
                transform: rotate(45deg) !important;
            }

            #admin_bar[data-state="expanded"][data-mobile-state="open"] .cms-admin-bar-hamburger-line:last-child {
                top: 0 !important;
                transform: rotate(-45deg) !important;
            }
        }

        @media (min-width: 783px) and (max-width: 1024px) {
            .cms-admin-bar-main-link,
            .cms-admin-bar-main-button {
                padding: 0 10px !important;
            }
        }
    </style>

    <div id="admin_bar" data-state="{{ $isCollapsed ? 'collapsed' : 'expanded' }}" data-mobile-state="closed">
        <a id="adminbar-restore-btn"
            class="cms-admin-bar-restore-btn"
            href="#"
            title="Powered by {{ config('astero.branding.name', 'Astero') }}">
            <img src="{{ config('astero.branding.icon') ?: asset('icon-lightmode.svg') }}"
                alt="{{ config('astero.branding.name', 'Astero') }}">
        </a>

        <button id="adminbar-mobile-toggle"
            class="cms-admin-bar-mobile-toggle"
            type="button"
            aria-label="Toggle admin toolbar menu">
            <span class="cms-admin-bar-hamburger">
                <span class="cms-admin-bar-hamburger-line"></span>
                <span class="cms-admin-bar-hamburger-line"></span>
            </span>
        </button>

        <nav id="adminbar-menu" class="cms-admin-bar-menu">
            <div id="adminbar-primary" class="cms-admin-bar-primary">
                <a href="{{ route('dashboard') }}" class="cms-admin-bar-main-link">
                    <span class="cms-admin-bar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                    </span>
                    <span>Dashboard</span>
                </a>

                <div class="cms-admin-bar-dropdown">
                    <button data-adminbar-trigger class="cms-admin-bar-main-button" type="button">
                        <span class="cms-admin-bar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </span>
                        <span>Appearance</span>
                        <span aria-hidden="true" class="cms-admin-bar-dropdown-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div data-adminbar-menu-panel data-adminbar-align="left" class="cms-admin-bar-menu-panel">
                        <a href="{{ route('cms.appearance.themes.index') }}" class="cms-admin-bar-submenu-link">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5"></path>
                                    <path d="M2 12l10 5 10-5"></path>
                                </svg>
                            </span>
                            Themes
                        </a>
                        <a href="{{ route('cms.appearance.menus.index') }}" class="cms-admin-bar-submenu-link">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="12" x2="21" y2="12"></line>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <line x1="3" y1="18" x2="21" y2="18"></line>
                                </svg>
                            </span>
                            Menus
                        </a>
                        <a href="{{ route('cms.appearance.widgets.index') }}" class="cms-admin-bar-submenu-link">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="3" y1="9" x2="21" y2="9"></line>
                                    <line x1="9" y1="21" x2="9" y2="9"></line>
                                </svg>
                            </span>
                            Widgets
                        </a>
                    </div>
                </div>

                @canany(['add_posts', 'add_categories', 'add_tags', 'add_pages'])
                    <div class="cms-admin-bar-dropdown">
                        <button data-adminbar-trigger class="cms-admin-bar-main-button" type="button">
                            <span class="cms-admin-bar-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </span>
                            <span>New</span>
                            <span aria-hidden="true" class="cms-admin-bar-dropdown-arrow">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </button>
                        <div data-adminbar-menu-panel data-adminbar-align="left" class="cms-admin-bar-menu-panel">
                            @can('add_posts')
                                <a href="{{ route('cms.posts.create') }}" class="cms-admin-bar-submenu-link">
                                    <span class="cms-admin-bar-submenu-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </span>
                                    Post
                                </a>
                            @endcan
                            @can('add_categories')
                                <a href="{{ route('cms.categories.create') }}" class="cms-admin-bar-submenu-link">
                                    <span class="cms-admin-bar-submenu-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                    </span>
                                    Category
                                </a>
                            @endcan
                            @can('add_tags')
                                <a href="{{ route('cms.tags.create') }}" class="cms-admin-bar-submenu-link">
                                    <span class="cms-admin-bar-submenu-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                        </svg>
                                    </span>
                                    Tag
                                </a>
                            @endcan
                            @can('add_pages')
                                <a href="{{ route('cms.pages.create') }}" class="cms-admin-bar-submenu-link">
                                    <span class="cms-admin-bar-submenu-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                    </span>
                                    Page
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @if (! empty($editLink))
                    <a href="{{ $editLink['url'] }}" class="cms-admin-bar-main-link cms-admin-bar-edit-link">
                        <span class="cms-admin-bar-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </span>
                        <span>{{ $editLink['label'] }}</span>
                    </a>
                @endif
            </div>

            <div id="adminbar-secondary" class="cms-admin-bar-secondary">
                <div class="cms-admin-bar-dropdown">
                    <button data-adminbar-trigger class="cms-admin-bar-main-button" type="button">
                        <span class="cms-admin-bar-user-avatar">
                            @if(Auth::user()->avatar)
                                <img src="{{ Auth::user()->avatar_image }}" alt="{{ Auth::user()->name }}">
                            @else
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            @endif
                        </span>
                        <span class="cms-admin-bar-user-name">{{ ucwords(Auth::user()->name) }}</span>
                        <span aria-hidden="true" class="cms-admin-bar-dropdown-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div data-adminbar-menu-panel data-adminbar-align="right" class="cms-admin-bar-menu-panel">
                        <a href="{{ route('app.profile') }}" class="cms-admin-bar-submenu-link">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            Profile
                        </a>
                        <a href="{{ route('dashboard') }}" class="cms-admin-bar-submenu-link">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                                </svg>
                            </span>
                            Dashboard
                        </a>
                        <div class="cms-admin-bar-divider"></div>
                        <a href="#" onclick="submitLogoutForm()" class="cms-admin-bar-submenu-link cms-admin-bar-logout">
                            <span class="cms-admin-bar-submenu-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                            </span>
                            {{ __('general.logout') }}
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <button id="adminbar-close-btn"
            class="cms-admin-bar-close-btn"
            type="button"
            title="Hide toolbar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <form id="admin-logout-form" style="display:none;" action="{{ route('logout') }}" method="POST">
        @csrf
    </form>

    <script data-up-execute>
        (function() {
            const adminBar = document.getElementById('admin_bar');
            if (!adminBar) {
                return;
            }

            const menu = document.getElementById('adminbar-menu');
            const closeButton = document.getElementById('adminbar-close-btn');
            const restoreButton = document.getElementById('adminbar-restore-btn');
            const mobileToggle = document.getElementById('adminbar-mobile-toggle');
            const dropdownWrappers = Array.from(adminBar.querySelectorAll('.cms-admin-bar-dropdown'));
            const PREVIEW_BANNER_HEIGHT = document.getElementById('preview_banner') ? 36 : 0;
            const MOBILE_BREAKPOINT = 782;

            // Get computed bar height from CSS variable
            function getBarHeight() {
                return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--ab-height')) || 36;
            }

            let resizeTimeout;
            let lastWidth = window.innerWidth;

            function setBodyOffset(visible) {
                const barHeight = getBarHeight();
                const offset = visible ? (barHeight + PREVIEW_BANNER_HEIGHT) : PREVIEW_BANNER_HEIGHT;
                document.body.style.paddingTop = offset + 'px';
            }

            function isMobile() {
                return window.innerWidth <= MOBILE_BREAKPOINT;
            }

            function applyState(state) {
                adminBar.dataset.state = state;
                setBodyOffset(state === 'expanded');
            }

            function persistState(state) {
                const expires = new Date(Date.now() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = 'adminbar-visible=' + (state === 'expanded' ? 1 : 0) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
            }

            function updateAdminbarStateInternal(state) {
                persistState(state);
                applyState(state);
            }

            function setDropdownVisibility(panel, visible) {
                if (panel) {
                    if (visible) {
                        panel.classList.add('visible');
                        // Small delay for animation
                        requestAnimationFrame(() => {
                            panel.style.display = 'flex';
                        });
                    } else {
                        panel.classList.remove('visible');
                        // Allow animation to complete before hiding
                        setTimeout(() => {
                            if (!panel.classList.contains('visible')) {
                                panel.style.display = 'none';
                            }
                        }, 150);
                    }
                }
            }

            function closeAllDropdowns() {
                dropdownWrappers.forEach((wrapper) => {
                    const panel = wrapper.querySelector('.cms-admin-bar-menu-panel');
                    wrapper.classList.remove('open');
                    if (panel) {
                        setDropdownVisibility(panel, false);
                    }
                });
            }

            // Dropdown interactions
            dropdownWrappers.forEach((wrapper) => {
                const trigger = wrapper.querySelector('[data-adminbar-trigger]');
                const panel = wrapper.querySelector('.cms-admin-bar-menu-panel');

                if (!trigger || !panel) {
                    return;
                }

                let isOpen = false;

                const closePanel = () => {
                    isOpen = false;
                    wrapper.classList.remove('open');
                    setDropdownVisibility(panel, false);
                };

                const openPanel = () => {
                    if (adminBar.dataset.state !== 'expanded') {
                        closePanel();
                        return;
                    }
                    isOpen = true;
                    wrapper.classList.add('open');
                    setDropdownVisibility(panel, true);
                };

                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (isOpen) {
                        closePanel();
                    } else {
                        // On mobile, don't close other dropdowns
                        if (!isMobile()) {
                            dropdownWrappers.forEach((other) => {
                                if (other === wrapper) {
                                    return;
                                }
                                const otherPanel = other.querySelector('.cms-admin-bar-menu-panel');
                                other.classList.remove('open');
                                setDropdownVisibility(otherPanel, false);
                            });
                        }
                        openPanel();
                    }
                });

                let closeTimeout = null;

                if (!isMobile()) {
                    wrapper.addEventListener('mouseenter', () => {
                        // Clear any pending close timeout
                        if (closeTimeout) {
                            clearTimeout(closeTimeout);
                            closeTimeout = null;
                        }
                        if (window.innerWidth > MOBILE_BREAKPOINT && adminBar.dataset.state === 'expanded') {
                            dropdownWrappers.forEach((other) => {
                                if (other === wrapper) {
                                    return;
                                }
                                const otherPanel = other.querySelector('.cms-admin-bar-menu-panel');
                                other.classList.remove('open');
                                setDropdownVisibility(otherPanel, false);
                            });
                            openPanel();
                        }
                    });

                    wrapper.addEventListener('mouseleave', () => {
                        if (window.innerWidth > MOBILE_BREAKPOINT) {
                            // Add delay before closing to allow mouse to reach dropdown
                            closeTimeout = setTimeout(() => {
                                closePanel();
                                closeTimeout = null;
                            }, 1500);
                        }
                    });
                }

                // Only close on outside click on desktop
                if (!isMobile()) {
                    document.addEventListener('click', (event) => {
                        if (!wrapper.contains(event.target)) {
                            closePanel();
                        }
                    });
                }
            });

            if (restoreButton) {
                restoreButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    const nextState = adminBar.dataset.state === 'expanded' ? 'collapsed' : 'expanded';
                    closeAllDropdowns();
                    updateAdminbarStateInternal(nextState);
                });
            }

            if (closeButton) {
                closeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    closeAllDropdowns();
                    updateAdminbarStateInternal('collapsed');
                });
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (adminBar.dataset.mobileState === 'open') {
                        adminBar.dataset.mobileState = 'closed';
                        closeAllDropdowns();
                    } else {
                        adminBar.dataset.mobileState = 'open';
                    }
                });
            }

            // Close mobile menu when clicking outside
            document.addEventListener('click', (event) => {
                if (isMobile() && adminBar.dataset.mobileState === 'open') {
                    if (!adminBar.contains(event.target)) {
                        adminBar.dataset.mobileState = 'closed';
                        closeAllDropdowns();
                    }
                }
            });

            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const currentWidth = window.innerWidth;
                    if ((lastWidth <= MOBILE_BREAKPOINT && currentWidth > MOBILE_BREAKPOINT) ||
                        (lastWidth > MOBILE_BREAKPOINT && currentWidth <= MOBILE_BREAKPOINT)) {
                        closeAllDropdowns();
                        if (currentWidth > MOBILE_BREAKPOINT) {
                            adminBar.dataset.mobileState = 'closed';
                        }
                        // Re-apply body offset on resize
                        setBodyOffset(adminBar.dataset.state === 'expanded');
                    }
                    lastWidth = currentWidth;
                }, 150);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeAllDropdowns();
                    if (isMobile() && adminBar.dataset.mobileState === 'open') {
                        adminBar.dataset.mobileState = 'closed';
                    }
                }
            });

            applyState(adminBar.dataset.state === 'expanded' ? 'expanded' : 'collapsed');

            window.updateAdminbarState = (value) => {
                const desiredState = value === 1 ? 'expanded' : 'collapsed';
                closeAllDropdowns();
                updateAdminbarStateInternal(desiredState);
            };
        })();

        function submitLogoutForm() {
            const logoutForm = document.getElementById('admin-logout-form');
            if (logoutForm) {
                logoutForm.submit();
            }
        }
    </script>
@endif
