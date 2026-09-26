import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/Types';

/** Typed page props (shared + page specific). */
export function useShared<T extends SharedProps = SharedProps>(): T {
    return usePage<T>().props as T;
}
