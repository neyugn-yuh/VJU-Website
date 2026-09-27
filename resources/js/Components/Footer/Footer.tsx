import { useShared } from '@/Hooks/useShared';
import { PhoneIcon, SocialIcons } from '../Navigation/Icons';
import SmartLink from '../Navigation/SmartLink';

const FOOTER_GROUPS = [
    {
        title: 'Đảm bảo chất lượng',
        links: ['Văn bản hướng dẫn', 'Hệ thống đảm bảo chất lượng', 'Tin tức, thông báo', 'Hỗ trợ đổi mới giảng dạy'],
    },
    {
        title: 'Tuyển sinh',
        links: ['Cập nhật tuyển sinh', 'Hướng dẫn đăng ký', 'Chương trình', 'FAQ', 'Chỗ ở', 'Học phí', 'Học bổng'],
    },
    {
        title: 'Nghiên cứu',
        links: ['Tổng quan nghiên cứu', 'Lĩnh vực nghiên cứu chính', 'Khả năng nghiên cứu', 'Ấn phẩm nghiên cứu'],
    },
];

const FALLBACK_SOCIAL = {
    facebook: 'https://www.facebook.com/vnu.vju',
    instagram: 'https://www.instagram.com/vnu.vju',
    youtube: 'https://www.youtube.com/@VietnamJapanUniversity',
};

function FloatingActions() {
    return (
        <aside className="vju-floating-actions" aria-label="Tư vấn tuyển sinh">
            <a href="#admission" className="vju-float-button vju-float-green">TƯ VẤN ĐẠI<br />HỌC</a>
            <a href="#admission" className="vju-float-button vju-float-green">TƯ VẤN THẠC<br />SĨ</a>
            <a href="https://tuyensinh.vju.ac.vn/" target="_blank" rel="noopener noreferrer" className="vju-float-button vju-float-red">NỘP HỒ SƠ<br />ONLINE</a>
            <div className="vju-float-socials">
                <a href="tel:+84966954736" aria-label="Gọi tư vấn" className="vju-phone-bubble"><PhoneIcon width={30} height={30} /></a>
                <a href="https://zalo.me/0966954736" target="_blank" rel="noopener noreferrer" aria-label="Zalo" className="vju-zalo-bubble">Zalo</a>
            </div>
        </aside>
    );
}

export function PublicFloatingActions() {
    return <FloatingActions />;
}

export default function Footer() {
    const { site, menus } = useShared();
    const { email, address } = site.contact ?? {};
    const social = { ...FALLBACK_SOCIAL, ...(site.social ?? {}) };
    const footerItems = menus?.footer ?? [];

    return (
        <footer className="vju-footer">
            <div className="vju-footer-main container-site">
                <div className="vju-footer-contact">
                    <img src="/assets/vju-logo.png" alt={site.name} width={444} height={107} className="vju-footer-logo" />
                    <ul className="vju-footer-contact-list">
                        <li><strong>HOTLINE</strong><br />Hotline tuyển sinh:<br />+ (+84) 966 954 736<br />+ (+84) 969 638 426<br />Liên hệ chung: + 024.7306.6001</li>
                        <li><strong>EMAIL</strong><br /><a href={`mailto:${email ?? 'admission@vju.ac.vn'}`}>{email ?? 'admission@vju.ac.vn'}</a></li>
                    </ul>
                </div>

                <div className="vju-footer-address">
                    <h2>Cơ sở VJU</h2>
                    <div className="vju-map-placeholder" role="img" aria-label="Bản đồ cơ sở Đại học Việt Nhật">ĐẠI HỌC VIỆT NHẬT</div>
                    <p><strong>CƠ SỞ MỸ ĐÌNH:</strong><br />{address || 'Đường Lưu Hữu Phước, phường Từ Liêm, thành phố Hà Nội'}</p>
                    <p><strong>CƠ SỞ HÒA LẠC:</strong><br />Khu đô thị Đại học Quốc gia Hà Nội tại Hòa Lạc, Hà Nội</p>
                </div>

                {FOOTER_GROUPS.map((group) => (
                    <nav key={group.title} className="vju-footer-column" aria-label={group.title}>
                        <h2>{group.title}</h2>
                        <ul>
                            {group.links.map((label) => {
                                const match = footerItems.find((item) => item.label.toLowerCase() === label.toLowerCase());
                                return <li key={label}>{match ? <SmartLink href={match.url} target={match.target}>{label}</SmartLink> : <a href="#">{label}</a>}</li>;
                            })}
                        </ul>
                    </nav>
                ))}
            </div>

            <div className="vju-footer-follow container-site">
                <div>
                    <h2>Theo dõi</h2>
                    <ul className="vju-social-list">
                        {Object.entries(social).filter(([, url]) => typeof url === 'string' && url).map(([key, url]) => {
                            const Icon = SocialIcons[key];
                            return <li key={key}><a href={url as string} target="_blank" rel="noopener noreferrer" aria-label={key}>{Icon ? <Icon width={24} height={24} /> : key.slice(0, 2)}</a></li>;
                        })}
                    </ul>
                </div>
                <div className="vju-footer-brand-text">{site.description || 'Trường Đại học Việt Nhật, Đại học Quốc gia Hà Nội'}</div>
            </div>

            <div className="vju-footer-bottom"><div className="container-site">© {new Date().getFullYear()} Vietnam Japan University · VNU</div></div>
        </footer>
    );
}
