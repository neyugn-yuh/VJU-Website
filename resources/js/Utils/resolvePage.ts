import type { ComponentType } from 'react';

const pages = import.meta.glob<{ default: ComponentType }>('../Pages/**/*.tsx', { eager: true });

export function resolvePage(name: string) {
    const page = pages[`../Pages/${name}.tsx`];
    if (!page) {
        throw new Error(`Page not found: ${name}`);
    }
    return page.default;
}
