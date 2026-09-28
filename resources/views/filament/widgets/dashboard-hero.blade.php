<div class="vju-linear-hero">
    <div class="vju-hero-glow"></div>
    <div class="vju-hero-content">
        <div class="vju-hero-header">
            <div class="vju-hero-info">
                <div class="vju-hero-status-row">
                    <span class="vju-hero-badge">
                        <span class="vju-status-dot"></span>
                        {{ __('admin.system_ready') }}
                    </span>
                    <span class="vju-hero-date">{{ $today }}</span>
                </div>
                <h1 class="vju-hero-greeting">
                    {{ $greeting }}, <span class="vju-hero-name">{{ $user?->name ?? __('admin.admin_user') }}</span>
                </h1>
                <p class="vju-hero-sub">
                    {{ __('admin.subheading') }}
                </p>
            </div>

            <div class="vju-hero-pills-status">
                <div class="vju-mini-stat">
                    <span class="vju-mini-stat-val text-emerald-500">{{ number_format($publishedCount) }}</span>
                    <span class="vju-mini-stat-lbl">{{ __('admin.published') }}</span>
                </div>
                <div class="vju-mini-divider"></div>
                <div class="vju-mini-stat">
                    <span class="vju-mini-stat-val {{ $pendingCount > 0 ? 'text-amber-500' : 'text-slate-400' }}">
                        {{ number_format($pendingCount) }}
                    </span>
                    <span class="vju-mini-stat-lbl">{{ __('admin.pending_review') }}</span>
                </div>
                <div class="vju-mini-divider"></div>
                <div class="vju-mini-stat">
                    <span class="vju-mini-stat-val text-indigo-400">{{ number_format($draftsCount) }}</span>
                    <span class="vju-mini-stat-lbl">{{ __('admin.drafts') }}</span>
                </div>
            </div>
        </div>

        <div class="vju-hero-actions-container">
            <div class="vju-hero-actions-label">
                <svg class="vju-icon-bolt" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                {{ __('admin.quick_actions') }}
            </div>

            <div class="vju-command-pills">
                <a href="{{ url('/admin/contents/create?type=post') }}" class="vju-command-pill" title="{{ __('admin.new_post') }} (C / N)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <span>{{ __('admin.new_post') }}</span>
                    <kbd>C</kbd>
                </a>

                <a href="{{ url('/admin/contents/create?type=page') }}" class="vju-command-pill" title="{{ __('admin.new_page') }} (P)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                        <line x1="12" x2="12" y1="8" y2="16"/>
                        <line x1="8" x2="16" y1="12" y2="12"/>
                    </svg>
                    <span>{{ __('admin.new_page') }}</span>
                    <kbd>P</kbd>
                </a>

                <a href="{{ url('/admin/media') }}" class="vju-command-pill" title="{{ __('admin.media_library') }} (M)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                        <circle cx="9" cy="9" r="2"/>
                        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                    </svg>
                    <span>{{ __('admin.media_library') }}</span>
                    <kbd>M</kbd>
                </a>

                <a href="{{ url('/admin/manage-site') }}" class="vju-command-pill" title="{{ __('admin.site_settings') }} (S)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span>{{ __('admin.site_settings') }}</span>
                    <kbd>S</kbd>
                </a>

                <a href="{{ url('/') }}" target="_blank" rel="noopener noreferrer" class="vju-command-pill vju-command-pill-primary" title="{{ __('admin.view_live') }} (V)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" x2="22" y1="12" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <span>{{ __('admin.view_live') }}</span>
                    <kbd>V</kbd>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        if (window.__vjuLinearShortcutsBound) return;
        window.__vjuLinearShortcutsBound = true;

        document.addEventListener('keydown', function(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable) {
                return;
            }
            if (e.metaKey || e.ctrlKey || e.altKey) return;

            const key = e.key.toLowerCase();
            if (key === 'c' || key === 'n') {
                e.preventDefault();
                window.location.href = '{{ url('/admin/contents/create?type=post') }}';
            } else if (key === 'p') {
                e.preventDefault();
                window.location.href = '{{ url('/admin/contents/create?type=page') }}';
            } else if (key === 'm') {
                e.preventDefault();
                window.location.href = '{{ url('/admin/media') }}';
            } else if (key === 's') {
                e.preventDefault();
                window.location.href = '{{ url('/admin/manage-site') }}';
            } else if (key === 'v') {
                e.preventDefault();
                window.open('{{ url('/') }}', '_blank');
            }
        });
    })();
</script>
