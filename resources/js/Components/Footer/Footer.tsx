import { useShared } from '@/Hooks/useShared';
import { PhoneIcon, SocialIcons } from '../Navigation/Icons';
import SmartLink from '../Navigation/SmartLink';

// Link groups and targets copied from the crawled vju.ac.vn footer (row by row, three per row).
const FOOTER_GROUPS: { title: string; links: [string, string][] }[] = [
    {
        title: 'Đảm bảo chất lượng',
        links: [
            ['Văn bản hướng dẫn', '/en/guidelines/'],
            ['Hệ thống đảm bảo chất lượng', '/en/quality-assurance-system-qa/'],
            ['Tin tức, thông báo', '/about-vju-vn/media-vn/'],
            ['Hỗ trợ đổi mới giảng dạy', '/en/support-teaching-innovation/'],
        ],
    },
    {
        title: 'Nghiên Cứu',
        links: [
            ['Tổng quan nghiên cứu', '/collaboration-vn/overview-vn/'],
            ['Lĩnh vực nghiên cứu chính', '/nghien-cuu/linh-vuc-nghien-cuu-chinh/'],
            ['Khả năng nghiên cứu', '/nghien-cuu/nguon-kinh-phi/'],
            ['Ấn phẩm nghiên cứu', '/nghien-cuu/hop-tac-nghien-cuu-quoc-te/'],
        ],
    },
    {
        title: 'Hợp Tác',
        links: [
            ['Hợp tác doanh nghiệp', '/collaboration-vn/enterprises-collaboration-vn/'],
            ['Đối tác đại học', '/collaboration-vn/partner-university-vn/'],
            ['Cơ hội học tập tại nước ngoài', '/collaboration-vn/co-hoi-hoc-tap-tai-nuoc-ngoai/'],
            ['Tổng quan về hợp tác', '/collaboration-vn/overview-vn/'],
        ],
    },
    {
        title: 'Tuyển Sinh',
        links: [
            ['Cập nhật tuyển sinh', '/en/admission-update/'],
            ['Hướng dẫn đăng ký', '/en/admissions/how-to-apply/'],
            ['Chương trình', '/en/admissions/'],
            ['FAQ', '/en/faq/'],
            ['Chỗ ở', '/en/accomodation/'],
            ['Phí nhập học và phương thức thanh toán', '/en/admissions/tuition-fee/'],
            ['Học bổng', '/admissions-vn/scholarships-vn/'],
        ],
    },
    {
        title: 'Đào Tạo',
        links: [
            ['Giới thiệu', '/gioi-thieu-2/'],
            ['Sau đại học', '/sau-dai-hoc/'],
            ['Đại học', '/trang-chu/dao-tao/dai-hoc/'],
            ['Thực tập và các chuyến đi thực địa', '/en/academics/internship/'],
            ['Khóa học ngắn', '/en/vju-services/short-courses/'],
            ['Tài liệu và hướng dẫn', '/en/academics/documents-and-guidelines/'],
            ['Thư viện VNU-LIC, VJU', 'https://lic.vnu.edu.vn/'],
        ],
    },
    {
        title: 'Về Chúng Tôi',
        links: [
            ['Thông điệp từ Hiệu trưởng', '/about-vju-vn/message-from-the-rector-vn/'],
            ['Sứ Mệnh, Tầm Nhìn Và Triết Lý Giáo Dục Lịch Sử', '/about-vju-vn/message-from-the-rector-vn/#mission-vision-id-vn'],
            ['Cơ cấu tổ chức', '/about-vju-vn/message-from-the-rector-vn/#Organizational-Structure-vn'],
            ['Khuôn viên và cơ sở vật chất của VJU', '/sinh-vien/khuon-vien-va-co-so-vat-chat-cua-vju/'],
            ['Truyền thông', '/about-vju-vn/media-vn/'],
        ],
    },
];

const FOOTER_MAPS = [
    {
        label: 'Cơ sở Mỹ Đình',
        src: 'https://maps.google.com/maps?q=Vietnam%20Japan%20University%20(VJU)%20M%E1%BB%B9%20%C4%90%C3%ACnh%2C%20Ph%E1%BB%91%20L%C6%B0u%20H%E1%BB%AFu%20Ph%C6%B0%E1%BB%9Bc%2C%20M%E1%BB%B9%20%C4%90%C3%ACnh%201%2C%20C%E1%BA%A7u%20Di%E1%BB%85n%2C%20Nam%20T%E1%BB%AB%20Li%C3%AAm%2C%20H%C3%A0%20N%E1%BB%99i&t=m&z=15&output=embed&iwloc=near',
    },
    {
        label: 'Cơ sở Hòa Lạc',
        src: 'https://maps.google.com/maps?q=2F3R%2B689%2C%20Th%E1%BA%A1ch%20Ho%C3%A0%2C%20Th%E1%BA%A1ch%20Th%E1%BA%A5t%2C%20H%C3%A0%20N%E1%BB%99i%2C%20Vietnam&t=m&z=15&output=embed&iwloc=near',
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
    const { site } = useShared();
    const { email, address } = site.contact ?? {};
    const social = { ...FALLBACK_SOCIAL, ...(site.social ?? {}) };

    return (
        <footer className="vju-footer">
            <div className="vju-footer-main container-site">
                <div className="vju-footer-brand">
                    <img src="/assets/vju-logo-footer.svg" alt={site.name} width={186} height={71} className="vju-footer-logo" />
                    <div className="vju-footer-maps">
                        {FOOTER_MAPS.map((map) => (
                            <iframe key={map.label} src={map.src} title={map.label} loading="lazy" />
                        ))}
                    </div>
                </div>

                <div className="vju-footer-contact">
                    <p><strong>HOTLINE:</strong><br />Hotline Tuyển sinh:<br />+ (+84) 966 954 736<br />+ (+84) 969 638 426<br />Liên hệ chung:<br />+ 024.7306.6001</p>
                    <p>
                        <strong>EMAIL:</strong> <a href={`mailto:${email ?? 'admission@vju.ac.vn'}`}>{email ?? 'admission@vju.ac.vn'}</a>
                        <br /><a href="mailto:info@vju.ac.vn">info@vju.ac.vn</a>
                    </p>
                    <p><strong>CƠ SỞ MỸ ĐÌNH:</strong><br />{address || 'Đường Lưu Hữu Phước, phường Từ Liêm, thành phố Hà Nội'}</p>
                    <p><strong>CƠ SỞ HÒA LẠC:</strong><br />Khu QGHN04, Khu đô thị Đại học Quốc gia Hà Nội tại Hòa Lạc, Xã Hòa Lạc, Thành phố Hà Nội</p>
                    <h2>THEO DÕI</h2>
                    <ul className="vju-social-list">
                        {Object.entries(social).filter(([, url]) => typeof url === 'string' && url).map(([key, url]) => {
                            const Icon = SocialIcons[key];
                            return <li key={key}><a href={url as string} target="_blank" rel="noopener noreferrer" aria-label={key}>{Icon ? <Icon width={22} height={22} /> : key.slice(0, 2)}</a></li>;
                        })}
                    </ul>
                </div>

                <div className="vju-footer-links">
                    {FOOTER_GROUPS.map((group) => (
                        <nav key={group.title} className="vju-footer-column" aria-label={group.title}>
                            <h2>{group.title}</h2>
                            <ul>
                                {group.links.map(([label, url]) => <li key={label}><SmartLink href={url}>{label}</SmartLink></li>)}
                            </ul>
                        </nav>
                    ))}
                </div>
            </div>
        </footer>
    );
}
