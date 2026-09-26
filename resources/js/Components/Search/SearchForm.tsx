import { useId } from 'react';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import { SearchIcon } from '../Navigation/Icons';

/** Plain GET form to the current locale's /search/ page (works without JavaScript). */
export default function SearchForm({ q = '', size = 'sm' }: { q?: string; size?: 'sm' | 'lg' }) {
    const { locale, locales } = useShared();
    const t = useT();
    const id = useId();
    const action = `${locales.find((l) => l.code === locale)?.home ?? '/'}search/`;
    const lg = size === 'lg';

    return (
        <form role="search" method="get" action={action} className="flex w-full items-stretch">
            <label htmlFor={id} className="sr-only">
                {t('search')}
            </label>
            <input
                id={id}
                type="search"
                name="q"
                defaultValue={q}
                placeholder={t('search_placeholder')}
                minLength={2}
                maxLength={100}
                required
                className={`min-w-0 flex-1 rounded-l-md border border-r-0 border-line bg-white text-ink placeholder:text-muted ${
                    lg ? 'px-4 py-3 text-base' : 'px-3 py-1.5 text-sm'
                }`}
            />
            <button
                type="submit"
                className={`inline-flex items-center gap-2 rounded-r-md bg-primary-700 font-semibold text-white hover:bg-primary-800 ${
                    lg ? 'px-5 text-base' : 'px-3 text-sm'
                }`}
            >
                <SearchIcon width={lg ? 20 : 16} height={lg ? 20 : 16} />
                <span className={lg ? '' : 'sr-only'}>{t('search')}</span>
            </button>
        </form>
    );
}
