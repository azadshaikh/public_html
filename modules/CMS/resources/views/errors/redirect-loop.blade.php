<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Redirect Loop Detected</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 560px;
            width: 100%;
            overflow: hidden;
        }

        .error-header {
            background: #fee2e2;
            padding: 24px 32px;
            border-bottom: 1px solid #fecaca;
        }

        .error-icon {
            width: 48px;
            height: 48px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .error-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
        }

        .error-subtitle {
            font-size: 1rem;
            color: #b91c1c;
        }

        .error-body {
            padding: 32px;
        }

        .error-description {
            color: #374151;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .loop-visualization {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .loop-visualization-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .loop-chain {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .loop-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', monospace;
            font-size: 0.875rem;
        }

        .loop-path {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 4px;
            word-break: break-all;
        }

        .loop-path.current {
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
        }

        .loop-arrow {
            color: #9ca3af;
            font-size: 1rem;
        }

        .loop-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ef4444;
            font-weight: 500;
            margin-top: 12px;
            font-size: 0.875rem;
        }

        .loop-indicator svg {
            width: 16px;
            height: 16px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .help-section {
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
        }

        .help-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }

        .help-list {
            list-style: none;
            color: #4b5563;
            font-size: 0.9375rem;
        }

        .help-list li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .help-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 6px;
            height: 6px;
            background: #9ca3af;
            border-radius: 50%;
        }

        .error-footer {
            background: #f9fafb;
            padding: 16px 32px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .error-code {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4f46e5;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background: #4338ca;
        }

        .back-btn svg {
            width: 16px;
            height: 16px;
        }

        @media (max-width: 480px) {
            .error-header, .error-body, .error-footer {
                padding: 20px;
            }

            .error-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h1 class="error-title">Redirect Loop Detected</h1>
            <p class="error-subtitle">This page cannot be displayed</p>
        </div>

        <div class="error-body">
            <p class="error-description">
                The page you're trying to visit is caught in a redirect loop. This means the website's redirect rules are sending you in circles, preventing the page from loading.
            </p>

            @if(!empty($chain))
            <div class="loop-visualization">
                <div class="loop-visualization-title">Redirect Chain</div>
                <div class="loop-chain">
                    @foreach($chain as $index => $path)
                        <div class="loop-item">
                            <span class="loop-path {{ $index === count($chain) - 1 ? 'current' : '' }}">{{ $path }}</span>
                            @if($index < count($chain) - 1)
                                <span class="loop-arrow">→</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="loop-indicator">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Loop continues indefinitely
                </div>
            </div>
            @endif

            <div class="help-section">
                <h2 class="help-title">How to fix this</h2>
                <ul class="help-list">
                    <li>If you're the site administrator, check your redirect rules in the CMS Redirections panel</li>
                    <li>Look for circular redirects where page A redirects to page B, which redirects back to page A</li>
                    <li>Remove or update one of the conflicting redirect rules</li>
                    <li>Clear your browser cache and try again after fixing the rules</li>
                </ul>
            </div>
        </div>

        <div class="error-footer">
            <span class="error-code">Error: ERR_TOO_MANY_REDIRECTS</span>
            <a href="/" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Go Home
            </a>
        </div>
    </div>
</body>
</html>
