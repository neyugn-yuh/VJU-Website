import { Link } from '@inertiajs/react';
import { useShared } from '@/Hooks/useShared';
import type { MenuNode } from '@/Types';
import LanguageSwitcher from '../LanguageSwitcher/LanguageSwitcher';
import DesktopNav from '../Navigation/DesktopNav';
import MobileNav from '../Navigation/MobileNav';
import SearchForm from '../Search/SearchForm';

const TOP_LABELS = ['Tuyển sinh', 'Tin tức và sự kiện', 'Về VJU', 'Đăng nhập hệ thống', 'Đăng xuất'];
const MAIN_LABELS = ['Đào tạo', 'Sinh viên', 'Nghiên cứu', 'Hợp tác phát triển', 'Dịch vụ của VJU', 'Đảm bảo chất lượng', 'Khảo thí', 'VJU Fund', 'Tra cứu'];

function HeaderTools({ mobile = false }: { mobile?: boolean }) {
    return (
        <div className={mobile ? 'vju-mobile-tools' : 'vju-header-tools'}>
            <LanguageSwitcher />
            <SearchForm iconOnly={!mobile} />
        </div>
    );
}

export default function Header() {
    const { site, locale, locales, menus } = useShared();
    const home = locales.find((l) => l.code === locale)?.home ?? '/';
    const nav = menus?.header ?? [];
    const byLabel = (label: string) => nav.find((item) => item.label === label);
    const present = (item: MenuNode | undefined): item is MenuNode => Boolean(item);
    const topNav = TOP_LABELS.map(byLabel).filter(present);
    const mainNav = MAIN_LABELS.map(byLabel).filter(present);
    const mobileNav = [...topNav, ...mainNav];
    const logo = site.logo ?? '/assets/vju-logo.png';

    return (
        <header className="vju-header relative z-40">
            <div className="vju-header-primary">
                <div className="vju-header-inner">
                    <Link prefetch="hover" viewTransition href={home} className="vju-brand" aria-label={site.name}>
                        <img src={logo} alt={site.name} width={444} height={107} className="vju-brand-logo" />
                    </Link>

                    <div className="vju-primary-nav">
                        <DesktopNav items={topNav} />
                    </div>

                    <div className="vju-primary-tools">
                        <LanguageSwitcher />
                        <SearchForm iconOnly />
                    </div>

                    <MobileNav items={mobileNav}>
                        <HeaderTools mobile />
                    </MobileNav>
                </div>
            </div>

            <div className="vju-header-secondary">
                <div className="vju-header-inner">
                    <nav className="vju-secondary-nav" aria-label="Điều hướng chính">
                        <DesktopNav items={mainNav} />
                    </nav>
                </div>
            </div>
        </header>
    );
}
