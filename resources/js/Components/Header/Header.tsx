import { Link } from '@inertiajs/react';
import { useShared } from '@/Hooks/useShared';
import LanguageSwitcher from '../LanguageSwitcher/LanguageSwitcher';
import DesktopNav from '../Navigation/DesktopNav';
import { MailIcon, PhoneIcon } from '../Navigation/Icons';
import MobileNav from '../Navigation/MobileNav';
import SmartLink from '../Navigation/SmartLink';
import SearchForm from '../Search/SearchForm';

function TopBar() {
    const { menus, site } = useShared();
    const items = menus?.topbar ?? [];
    const { phone, email } = site.contact ?? {};
    if (!items.length && !phone && !email) return null;

    return (
        <div className="bg-primary-900 text-sm text-white">
            <div className="container-site flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-1.5">
                <ul className="flex flex-wrap items-center gap-x-5 gap-y-1">
                    {phone && (
                        <li>
                            <a href={`tel:${phone.replace(/[^\d+]/g, '')}`} className="inline-flex items-center gap-1.5 hover:underline">
                                <PhoneIcon width={14} height={14} />
                                {phone}
                            </a>
                        </li>
                    )}
                    {email && (
                        <li>
                            <a href={`mailto:${email}`} className="inline-flex items-center gap-1.5 hover:underline">
                                <MailIcon width={14} height={14} />
                                {email}
                            </a>
                        </li>
                    )}
                </ul>
                {items.length > 0 && (
                    <ul className="flex flex-wrap items-center gap-x-4 gap-y-1">
                        {items.map((item, i) => (
                            <li key={i}>
                                <SmartLink href={item.url} target={item.target} className="hover:underline">
                                    {item.label}
                                </SmartLink>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

export default function Header() {
    const { site, locale, locales, menus } = useShared();
    const home = locales.find((l) => l.code === locale)?.home ?? '/';
    const nav = menus?.header ?? [];

    return (
        <header className="relative z-40 border-b border-line bg-white">
            <TopBar />
            <div className="container-site flex items-center justify-between gap-4 py-3">
                <Link href={home} className="flex min-w-0 items-center gap-3">
                    {site.logo ? (
                        <img src={site.logo} alt={site.name} height={56} className="h-11 w-auto sm:h-14" />
                    ) : (
                        <span className="flex items-center gap-3">
                            <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-primary-700 text-lg font-extrabold text-white">
                                {site.name.slice(0, 1)}
                            </span>
                            <span className="line-clamp-2 max-w-[16rem] text-sm font-bold leading-tight text-primary-900 sm:max-w-md sm:text-lg">
                                {site.name}
                            </span>
                        </span>
                    )}
                </Link>
                <div className="hidden items-center gap-4 lg:flex">
                    <div className="w-64 xl:w-72">
                        <SearchForm />
                    </div>
                    <LanguageSwitcher />
                </div>
                <MobileNav items={nav}>
                    <SearchForm />
                    <LanguageSwitcher />
                </MobileNav>
            </div>
            {nav.length > 0 && (
                <div className="hidden border-t border-line lg:block">
                    <div className="container-site">
                        <DesktopNav items={nav} />
                    </div>
                </div>
            )}
        </header>
    );
}
