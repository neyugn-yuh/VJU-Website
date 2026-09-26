import { useShared } from './useShared';

/**
 * UI keys used by the frontend that lang/{vi,en,ja}/public.php does not define yet.
 * Shown only until the backend adds them (see frontend report).
 */
const MISSING: Record<string, string> = {
    pause: 'Pause',
    play: 'Play',
    play_video: 'Play video',
    copy_link: 'Copy link',
    link_copied: 'Link copied',
    on_this_page: 'On this page',
    number: 'Number',
    issued_at: 'Issued on',
    program: 'Program',
    academic_year: 'Academic year',
    work_type: 'Work type',
    website: 'Website',
};

/** t('results_count', { count: 3 }) -> replaces ":count". */
export function useT() {
    const { t } = useShared();

    return (key: string, replace: Record<string, string | number> = {}): string => {
        const value = typeof t?.[key] === 'string' ? t[key] : (MISSING[key] ?? key);
        return Object.entries(replace).reduce((s, [k, v]) => s.replaceAll(`:${k}`, String(v)), value);
    };
}
