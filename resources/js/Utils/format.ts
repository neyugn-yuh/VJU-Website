import type { Locale } from '@/Types';

const INTL: Record<Locale, string> = { vi: 'vi-VN', en: 'en-GB', ja: 'ja-JP' };

// Fixed zone so SSR (Node) and the browser render the same string (no hydration mismatch).
const TIME_ZONE = 'Asia/Ho_Chi_Minh';

export function formatDate(iso: string | null | undefined, locale: Locale, style: 'long' | 'short' = 'long'): string {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return iso;
    return new Intl.DateTimeFormat(INTL[locale] ?? locale, {
        year: 'numeric',
        month: style === 'long' ? 'long' : '2-digit',
        day: '2-digit',
        timeZone: TIME_ZONE,
    }).format(date);
}

/** "application/pdf" or ".../file.docx" -> "PDF" / "DOCX". */
export function fileKind(mime: string | null | undefined, url = ''): string {
    const ext = url.split(/[?#]/)[0].split('.').pop() ?? '';
    if (ext && ext.length <= 5 && !ext.includes('/')) return ext.toUpperCase();
    return mime?.split('/').pop()?.toUpperCase() ?? '';
}
