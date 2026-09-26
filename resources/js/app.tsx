import { createInertiaApp } from '@inertiajs/react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { resolvePage } from './Utils/resolvePage';

createInertiaApp({
    resolve: resolvePage,
    setup({ el, App, props }) {
        // Hydrate when the server rendered the page (Inertia SSR enabled), otherwise mount.
        if (el.hasChildNodes()) {
            hydrateRoot(el, <App {...props} />);
        } else {
            createRoot(el).render(<App {...props} />);
        }
    },
    progress: { color: '#c8102e' },
});
