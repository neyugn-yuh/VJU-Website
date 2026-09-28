import { Link } from '@inertiajs/react';
import { useShared } from '@/Hooks/useShared';
import type { MenuNode } from '@/Types';
import LanguageSwitcher from '../LanguageSwitcher/LanguageSwitcher';
import DesktopNav from '../Navigation/DesktopNav';
import MobileNav from '../Navigation/MobileNav';
import SearchForm from '../Search/SearchForm';

// Top (red) and main (white) rows per locale, in the order the crawled vju.ac.vn header shows them.
export const NAV_LABELS: Record<string, { top: string[]; main: string[] }> = {
    vi: {
        top: ['Tuyển sinh', 'Tin tức và sự kiện', 'Về VJU', 'Đăng nhập hệ thống', 'Đăng xuất'],
        main: ['Đào tạo', 'Sinh viên', 'Nghiên cứu', 'Hợp tác phát triển', 'Dịch vụ của VJU', 'Đảm bảo chất lượng', 'Khảo thí', 'VJU Fund', 'Tra cứu'],
    },
    en: {
        top: ['Internal for Staff', 'News & Events', 'About VJU'],
        main: ['Admissions', 'Academics', 'Student', 'Research', 'Collaboration', 'VJU’s Service', 'Quality assurance', 'Education Testing', 'VJU Fund'],
    },
    ja: {
        top: ['ニュース・インベント', '日越大学について'],
        main: ['入試・入学案内', '教育', '学生生活', '研究', '外部連携', '課外プログラム', '大学評価', '試験・評価', '寄付・ご支援'],
    },
};

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
    const labels = NAV_LABELS[locale] ?? NAV_LABELS.vi;
    const topNav = labels.top.map(byLabel).filter(present);
    const mainNav = labels.main.map(byLabel).filter(present);
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
