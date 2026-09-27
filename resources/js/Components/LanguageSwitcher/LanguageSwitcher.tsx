import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';

/**
 * Links to the real translation of the current page (props.alternates) or, when there is none,
 * to that language's home page. Plain <a>: switching language is a full page load.
 */
export default function LanguageSwitcher({ className = '' }: { className?: string }) {
    const { locale, locales, alternates } = useShared();
    const t = useT();

    const flags: Record<string, string> = {
        vi: '/assets/flag-vn.svg',
        en: '/assets/flag-gb.svg',
        ja: '/assets/flag-jp.svg',
    };

    return (
        <nav aria-label={t('language')} className={`vju-language-switcher ${className}`}>
            <ul className="flex items-center gap-2">
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
                                className={`inline-flex h-7 min-w-5 items-center justify-center rounded-sm px-0.5 transition-opacity ${current ? 'opacity-100' : 'opacity-75 hover:opacity-100'}`}
                            >
                                <img src={flags[l.code] ?? flags.vi} alt="" aria-hidden="true" className="h-[15px] w-[21px] object-cover" />
                                <span className="sr-only">{l.name}</span>
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
