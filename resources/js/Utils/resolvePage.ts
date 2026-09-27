import type { ComponentType } from 'react';

// Keep each page in its own chunk so the first visit does not download every
// public page. Inertia accepts the dynamic import promise and caches the
// resolved component for subsequent visits.
const pages = import.meta.glob<{ default: ComponentType }>('../Pages/**/*.tsx');

export function resolvePage(name: string) {
    const page = pages[`../Pages/${name}.tsx`];
    if (!page) {
        throw new Error(`Page not found: ${name}`);
    }
    return page().then((module) => module.default);
}
