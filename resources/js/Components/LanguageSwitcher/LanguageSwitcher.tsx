import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';

/**
 * Links to the real translation of the current page (props.alternates) or, when there is none,
 * to that language's home page. Plain <a>: switching language is a full page load.
 */
export default function LanguageSwitcher({ className = '' }: { className?: string }) {
    const { locale, locales, alternates } = useShared();
    const t = useT();

    return (
        <nav aria-label={t('language')} className={className}>
            <ul className="flex items-center gap-1">
                {locales.map((l) => {
                    const current = l.code === locale;
                    const href = (alternates ?? []).find((a) => a.locale === l.code)?.url ?? l.home;
                    return (
                        <li key={l.code}>
                            <a
                                href={href}
                                hrefLang={l.code}
                                lang={l.code}
                                aria-current={current ? 'true' : undefined}
                                title={l.name}
                                className={`inline-flex h-8 min-w-9 items-center justify-center rounded px-2 text-xs font-bold tracking-wide ${
                                    current ? 'bg-primary-700 text-white' : 'text-primary-900 hover:bg-primary-50'
                                }`}
                            >
                                <span aria-hidden="true">{l.short}</span>
                                <span className="sr-only">{l.name}</span>
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
