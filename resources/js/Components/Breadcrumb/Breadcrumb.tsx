import { Link } from '@inertiajs/react';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import type { Crumb } from '@/Types';
import { ChevronRight } from '../Navigation/Icons';

export default function Breadcrumb({ items, invert = false }: { items: Crumb[]; invert?: boolean }) {
    const { locale, locales } = useShared();
    const t = useT();
    const home = locales.find((l) => l.code === locale)?.home ?? '/';
    const crumbs: Crumb[] = [{ label: t('home'), url: home }, ...items];

    return (
        <nav aria-label={t('breadcrumb')} className={`text-sm ${invert ? 'text-white/85' : 'text-muted'}`}>
            <ol className="flex flex-wrap items-center gap-x-1 gap-y-1">
                {crumbs.map((c, i) => {
                    const last = i === crumbs.length - 1;
                    return (
                        <li key={i} className="flex items-center gap-1">
                            {i > 0 && <ChevronRight width={14} height={14} className="shrink-0 opacity-60" />}
                            {c.url && !last ? (
                                <Link prefetch="hover" viewTransition href={c.url} className={`underline-offset-2 hover:underline ${invert ? 'hover:text-white' : 'hover:text-primary-700'}`}>
                                    {c.label}
                                </Link>
                            ) : (
                                <span aria-current={last ? 'page' : undefined} className={`line-clamp-1 ${invert ? 'text-white' : 'text-ink'} font-medium`}>
                                    {c.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
