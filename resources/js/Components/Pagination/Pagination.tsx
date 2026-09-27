import { Link } from '@inertiajs/react';
import { useT } from '@/Hooks/useT';
import type { Pagination as PaginationData } from '@/Types';
import { ChevronLeft, ChevronRight } from '../Navigation/Icons';

const item = 'inline-flex min-w-10 h-10 items-center justify-center gap-1 rounded-md border px-3 text-sm font-medium';

export default function Pagination({ pagination }: { pagination: PaginationData | null }) {
    const t = useT();
    if (!pagination || pagination.last <= 1) return null;
    const { pages, prev, next, current, last } = pagination;

    return (
        <nav aria-label={t('page')} className="mt-10 flex justify-center">
            <ul className="flex flex-wrap items-center justify-center gap-2">
                {prev && (
                    <li>
                        <Link prefetch="hover" viewTransition href={prev} rel="prev" className={`${item} border-line bg-white text-primary-700 hover:border-primary-700`}>
                            <ChevronLeft width={16} height={16} />
                            <span>{t('previous')}</span>
                        </Link>
                    </li>
                )}
                {pages[0]?.n > 1 && (
                    <li aria-hidden="true" className="px-1 text-muted">
                        …
                    </li>
                )}
                {pages.map((p) => (
                    <li key={p.n}>
                        {p.n === current ? (
                            <span aria-current="page" className={`${item} border-primary-700 bg-primary-700 text-white`}>
                                <span className="sr-only">{t('page')} </span>
                                {p.n}
                            </span>
                        ) : (
                            <Link prefetch="hover" viewTransition href={p.url} className={`${item} border-line bg-white text-ink hover:border-primary-700 hover:text-primary-700`}>
                                <span className="sr-only">{t('page')} </span>
                                {p.n}
                            </Link>
                        )}
                    </li>
                ))}
                {pages[pages.length - 1]?.n < last && (
                    <li aria-hidden="true" className="px-1 text-muted">
                        …
                    </li>
                )}
                {next && (
                    <li>
                        <Link prefetch="hover" viewTransition href={next} rel="next" className={`${item} border-line bg-white text-primary-700 hover:border-primary-700`}>
                            <span>{t('next')}</span>
                            <ChevronRight width={16} height={16} />
                        </Link>
                    </li>
                )}
            </ul>
        </nav>
    );
}
