import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { resolvePage } from './Utils/resolvePage';

function setNavigationLoading(loading: boolean) {
    document.documentElement.classList.toggle('inertia-loading', loading);
    document.getElementById('main-content')?.setAttribute('aria-busy', loading ? 'true' : 'false');
}

function bindNavigationFeedback() {
    router.on('before', ({ detail: { visit } }) => {
        // Hover prefetches are intentionally invisible; only a real visit
        // should dim the current page and announce loading to the user.
        if (!visit.prefetch) setNavigationLoading(true);
    });
    router.on('finish', ({ detail: { visit } }) => {
        if (!visit.prefetch) setNavigationLoading(false);
    });
    // A click that reuses an in-flight prefetch does not create a second
    // request, so it has no matching finish event. Navigation is the reliable
    // completion signal for both normal and prefetched visits.
    router.on('navigate', () => setNavigationLoading(false));
}

createInertiaApp({
    resolve: resolvePage,
    setup({ el, App, props }) {
        bindNavigationFeedback();

        // Hydrate when the server rendered the page (Inertia SSR enabled), otherwise mount.
        if (el.hasChildNodes()) {
            hydrateRoot(el, <App {...props} />);
        } else {
            createRoot(el).render(<App {...props} />);
        }
    },
    progress: { color: '#c8102e', delay: 100, showSpinner: true },
});
