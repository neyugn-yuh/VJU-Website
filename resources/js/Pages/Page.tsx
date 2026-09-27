import BlockRenderer, { startsWithHero } from '@/Components/Blocks/BlockRenderer';
import Breadcrumb from '@/Components/Breadcrumb/Breadcrumb';
import Picture from '@/Components/Media/Picture';
import { MailIcon, PhoneIcon, PinIcon } from '@/Components/Navigation/Icons';
import SectionNav from '@/Components/Navigation/SectionNav';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import PublicLayout from '@/Layouts/PublicLayout';
import type { ContentFull, PageProps } from '@/Types';
import { blockAnchor, blockHeading } from '@/Utils/anchors';

const BANDS: Record<string, string> = {
    admissions: 'from-primary-800 via-primary-700 to-accent-700',
    education: 'from-primary-950 via-primary-800 to-primary-600',
    research: 'from-primary-950 via-primary-900 to-accent-800',
};

interface FormattedBody {
    html: string;
    variant: 'default' | 'program-directory' | 'news-directory' | 'event-directory' | 'research-directory' | 'document-directory' | 'staff-directory';
}

const LEGACY_PAGE_HEROES: Record<string, string> = {
    '/trang-chu/dao-tao/gioi-thieu/': '/storage/media/2023/10/derivatives/rectangle-22-web.png',
    '/collaboration-vn/tra-cuu-thong-tin-tot-nghiep/': '/storage/media/2023/10/derivatives/rectangle-22-1-web.jpg',
    '/tai-lieu-va-huong-dan-2/': '/storage/media/2023/10/derivatives/rectangle-23-1-web.jpg',
};

const LEGACY_CATEGORY_HEROES: Record<string, string> = {
    '/tin-tuc-va-su-kien/tin-tuc/': '/storage/media/2023/07/derivatives/news-banner-web.jpg',
    '/tin-tuc-va-su-kien/su-kien/': '/storage/media/2023/10/derivatives/rectangle-22-web.png',
    '/nghien-cuu/thong-tin-nhanh/': '/storage/media/2023/07/derivatives/research-web.jpg',
};

/**
 * A few migrated directory pages still contain the old WordPress markup:
 * section headings followed by image paragraphs and linked headings. Group
 * those pairs so the page can use the same responsive cards as the rest of
 * the site while leaving ordinary article HTML untouched.
 */
function formatLegacyBody(html: string): FormattedBody {
    const staffIntro = /^\s*<h2\b[^>]*>\s*GIỚI THIỆU\s*<\/h2>\s*(?=<h2\b[^>]*>\s*Phòng Đào tạo và Công tác sinh viên\s*<\/h2>)/i;
    const staffRecord = /(<p\b[^>]*>\s*<img\b[^>]*>\s*<\/p>)\s*(<ul\b[^>]*>[\s\S]*?<\/ul>)\s*(<p\b[^>]*>[\s\S]*?<\/(?:p)>)/i;
    const isStaffPage = staffIntro.test(html) && /<img\b[^>]*alt=["']Nguyen Thi An Hang["']/i.test(html);

    if (isStaffPage) {
        const withoutPageHeading = html.replace(staffIntro, '');
        const firstDivider = /<hr\b[^>]*>/i.exec(withoutPageHeading);

        if (firstDivider?.index !== undefined) {
            const intro = withoutPageHeading.slice(0, firstDivider.index);
            const staffMarkup = withoutPageHeading.slice(firstDivider.index + firstDivider[0].length);
            const records = Array.from(staffMarkup.matchAll(new RegExp(staffRecord.source, 'gi')), (match) => (
                `<article class="vju-staff-card">${match[1]}${match[2]}${match[3]}</article>`
            ));

            if (records.length >= 2) {
                return {
                    html: `${intro}<hr class="vju-staff-divider"><div class="vju-staff-grid">${records.join('')}</div>`,
                    variant: 'staff-directory',
                };
            }
        }
    }

    const researchImageParagraphs = Array.from(html.matchAll(/<p\b[^>]*>\s*(<img\b[^>]*>)\s*<\/p>/gi));
    const researchHeadings = Array.from(html.matchAll(/<h2\b[^>]*>([\s\S]*?)<\/h2>/gi), (match) => match[1]);
    const researchTextParagraphs = Array.from(html.matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi), (match) => match[1])
        .filter((paragraph) => !/<img\b/i.test(paragraph) && !/<a\b[^>]*>\s*Contact\s+Us\s*<\/a>/i.test(paragraph))
        .filter((paragraph) => paragraph.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').trim().length > 80);

    if (researchImageParagraphs.length >= 2 && /sustainable-global-university-of-innovation/i.test(html)) {
        const secondImageEnd = (researchImageParagraphs[1].index ?? 0) + researchImageParagraphs[1][0].length;
        const rawResearchStats = html.slice(secondImageEnd);
        const statsTitle = /<h2\b[^>]*>\s*(TIỀM LỰC KHCN)\s*<\/h2>/i.exec(rawResearchStats);
        const recommendationsStart = /<h2\b[^>]*>\s*Bạn cũng có thể thích\s*<\/h2>/i.exec(rawResearchStats)?.index;
        const statsMarkup = statsTitle?.index !== undefined
            ? rawResearchStats.slice(statsTitle.index + statsTitle[0].length, recommendationsStart ?? undefined)
            : '';
        const summaryStats = Array.from(statsMarkup.matchAll(/<p\b[^>]*>\s*(\d+)\s*<\/p>\s*<h4\b[^>]*>([\s\S]*?)<\/h4>/gi), (match) => (
            `<article class="vju-research-stat"><strong>${match[1]}</strong><p>${match[2].trim()}</p></article>`
        )).join('');
        const metricHeadings = Array.from(statsMarkup.matchAll(/<h2\b[^>]*>([\s\S]*?)<\/h2>/gi), (match) => match[1]);
        const researchMetrics: string[] = [];

        for (let index = 0; index < metricHeadings.length - 1; index += 1) {
            const value = metricHeadings[index].replace(/<[^>]+>/g, '').replace(/&nbsp;/gi, ' ').trim();

            if (!/^~?\d+(?:\.\d+)?$/.test(value)) continue;

            researchMetrics.push(`<article class="vju-research-metric"><strong>${value}</strong><p>${metricHeadings[index + 1]}</p></article>`);
            index += 1;
        }
        const pageHeading = researchHeadings.find((heading) => /Thông tin nhanh/i.test(heading)) ?? 'Thông tin nhanh';
        const featureHeading = researchHeadings.find((heading) => /Trường Đại học Việt Nhật/i.test(heading)) ?? 'Trường Đại học Việt Nhật (VJU)';
        const intro = researchTextParagraphs[0] ?? '';
        const continuation = researchTextParagraphs[1] ?? '';

        return {
            html: `
                <section class="vju-research-directory">
                    <h2>${pageHeading}</h2>
                    <div class="vju-research-feature vju-research-feature-primary">
                        <div class="vju-research-image">${researchImageParagraphs[0][1]}</div>
                        <div class="vju-research-copy">
                            <h3>${featureHeading}</h3>
                            <p>${intro}</p>
                        </div>
                    </div>
                    <div class="vju-research-feature vju-research-feature-secondary">
                        <div class="vju-research-copy"><p>${continuation}</p></div>
                        <div class="vju-research-image">${researchImageParagraphs[1][1]}</div>
                    </div>
                    <section class="vju-research-stats">
                        <h2 class="vju-research-stats-heading">TIỀM LỰC KHCN</h2>
                        <div class="vju-research-stats-summary">${summaryStats}</div>
                        <div class="vju-research-stats-metrics">${researchMetrics.join('')}</div>
                    </section>
                </section>`,
            variant: 'research-directory',
        };
    }

    const downloadCount = (html.match(/>\s*download\s*</gi) ?? []).length;
    const documentHeadings = Array.from(html.matchAll(/<h2\b[^>]*>([\s\S]*?)<\/h2>/gi));

    if (downloadCount >= 2 && documentHeadings.length >= 2 && /Tài liệu và hướng dẫn/i.test(documentHeadings[0][1])) {
        const sections = documentHeadings.slice(1).map((heading, index) => {
            const start = (heading.index ?? 0) + heading[0].length;
            const end = documentHeadings[index + 2]?.index ?? html.length;
            const sectionHtml = html.slice(start, end);
            const blocks = Array.from(sectionHtml.matchAll(/(<p\b[^>]*>[\s\S]*?<\/p>|<ul\b[^>]*>[\s\S]*?<\/ul>)/gi));
            const notices = blocks
                .filter((block) => /^<ul\b/i.test(block[1]))
                .map((block) => Array.from(block[1].matchAll(/<li\b[^>]*>([\s\S]*?)<\/li>/gi), (item) => item[1].replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim()))
                .flat()
                .filter(Boolean)
                .map((notice) => `<li>${notice}</li>`)
                .join('');
            const links = Array.from(sectionHtml.matchAll(/<p\b[^>]*>\s*<a\b[^>]*href=["']([^"']+)["'][^>]*>[\s\S]*?<\/a>\s*<\/p>/gi));
            const cards = links.map((link) => {
                const preceding = blocks.filter((block) => (block.index ?? 0) < (link.index ?? 0));
                const titleBlock = [...preceding].reverse().find((block) => /<b\b/i.test(block[1]))
                    ?? [...preceding].reverse().find((block) => /<strong\b/i.test(block[1]))
                    ?? preceding[preceding.length - 1];
                const title = titleBlock?.[1].replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim() ?? 'Tài liệu';

                return `<article class="vju-document-card">
                    <div class="vju-document-card-icon" aria-hidden="true">▤</div>
                    <h3>${title}</h3>
                    <div class="vju-document-card-actions">
                        <a href="${link[1]}" class="vju-document-view" target="_blank" rel="noreferrer" aria-label="Xem ${title}">◉</a>
                        <a href="${link[1]}" class="vju-document-download">Download <span aria-hidden="true">↓</span></a>
                    </div>
                </article>`;
            }).join('');

            return `<section class="vju-document-section">
                <h2>${heading[1]}</h2>
                ${notices ? `<ul class="vju-document-notices">${notices}</ul>` : ''}
                <div class="vju-document-grid">${cards}</div>
            </section>`;
        }).join('');

        return {
            html: `<div class="vju-documents-directory">${sections}</div>`,
            variant: 'document-directory',
        };
    }

    const eventHeadings = Array.from(html.matchAll(/<h2\b[^>]*>([\s\S]*?)<\/h2>/gi), (match) => match[1]).filter((heading) => !/Sự kiện/i.test(heading));
    const eventImages = Array.from(html.matchAll(/<img\b[^>]*>/gi), (match) => match[0]);
    const eventDescriptions = Array.from(html.matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi), (match) => match[1])
        .filter((paragraph) => !/<img\b/i.test(paragraph))
        .map((paragraph) => paragraph.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim())
        .filter((paragraph) => paragraph.length > 35);

    if (/<h2\b[^>]*>\s*Sự kiện\s*<\/h2>[\s\S]*?<h2\b[^>]*>\s*Sự kiện sắp tới\s*<\/h2>/i.test(html) && eventImages.length >= 2) {
        const cards = eventImages.map((image, index) => `
            <article class="vju-event-card">
                <div class="vju-event-card-image">${image}</div>
                <div class="vju-event-card-copy">
                    <h3>${eventHeadings[index] ?? 'Sự kiện'}</h3>
                    ${eventDescriptions[index] ? `<p>${eventDescriptions[index]}</p>` : ''}
                    <a href="#${index === 0 ? 'event-feature' : `event-${index}`}" class="vju-event-read-more">Đọc thêm</a>
                </div>
            </article>`).join('');

        return {
            html: `<section id="event-feature" class="vju-event-feature">${cards.split('</article>')[0]}</article></section><section class="vju-event-recent"><h2>Các Sự kiện gần đây</h2><div class="vju-event-grid">${cards}</div></section>`,
            variant: 'event-directory',
        };
    }

    const imageHeadingPair = /(<p\b[^>]*>[\s\S]*?<img\b[^>]*\/?\s*>[\s\S]*?<\/p>)\s*(<h[234]\b[^>]*>[\s\S]*?<\/h[234]>)/gi;
    const pairs = html.match(imageHeadingPair);
    const hasNewsFeature = /<p\b[^>]*>[\s\S]*?<img\b[\s\S]*?<\/p>\s*<h[34]\b[\s\S]*?<\/h[34]>\s*<p\b[\s\S]*?<\/p>\s*<p\b[\s\S]*?<\/p>/i.test(html);
    const hasNewsItems = /<ul\b[^>]*>[\s\S]*?<li\b[\s\S]*?<img\b[\s\S]*?<\/li>[\s\S]*?<\/ul>/i.test(html);
    if (hasNewsFeature && hasNewsItems) {
        // The old news page stores the featured item and the side list as
        // adjacent paragraphs/list markup. Restore the two-column structure
        // used by the crawled Elementor page without changing the content.
        const feature = /(<p\b[^>]*>[\s\S]*?<img\b[\s\S]*?<\/p>\s*<h[34]\b[\s\S]*?<\/h[34]>\s*<p\b[\s\S]*?<\/p>\s*<p\b[\s\S]*?<\/p>)/i;
        const sideList = /(<ul\b[^>]*>[\s\S]*?<\/ul>)/i;
        return {
            html: html
                .replace(/^\s*<h2\b[^>]*>\s*Tin tức\s*<\/h2>/i, '')
                .replace(feature, '<div class="vju-news-feature">$1</div>')
                .replace(sideList, '<div class="vju-news-side">$1</div>'),
            variant: 'news-directory',
        };
    }

    if (/^\s*<h2\b/i.test(html) && pairs && pairs.length >= 2) {
        return {
            html: html.replace(imageHeadingPair, '<div class="vju-program-card">$1$2</div>'),
            variant: 'program-directory',
        };
    }

    return { html, variant: downloadCount >= 2 ? 'document-directory' : 'default' };
}

function LegacyBody({ html }: { html: string }) {
    if (!html) return null;

    const formatted = formatLegacyBody(html);
    return (
        <div
            className={`prose-content mt-8 vju-legacy-body vju-legacy-body-${formatted.variant} vju-${formatted.variant}`}
            dangerouslySetInnerHTML={{ __html: formatted.html }}
        />
    );
}

function LegacyPageHero({ title, image, breadcrumbs, category = false }: { title: string; image: string; breadcrumbs: PageProps['breadcrumbs']; category?: boolean }) {
    return (
        <section className={`vju-legacy-page-hero ${category ? 'vju-category-hero' : ''}`}>
            <img src={image} alt="" aria-hidden="true" />
            <div className="vju-legacy-page-hero-overlay" aria-hidden="true" />
            <div className="container-site vju-legacy-page-hero-content">
                <Breadcrumb items={breadcrumbs} invert />
                <h1>{title}</h1>
            </div>
        </section>
    );
}

function LegacyCategoryAside({ research = false }: { research?: boolean }) {
    const links = research
        ? ['Tin tức nhanh', 'Các lĩnh vực nghiên cứu chính', 'Nhóm nghiên cứu', 'Hồ sơ chuyên gia', 'Nghiên cứu tiên tiến', 'Các dự án nghiên cứu', 'Cơ sở Dữ liệu Nghiên cứu', 'Cơ sở vật chất và trang thiết bị', 'Phòng thí nghiệm', 'Hợp tác nghiên cứu', 'Đề tài nghiên cứu khoa học các năm']
        : [];

    if (!links.length) return null;
    return (
        <aside className="vju-category-aside">
            <ul>
                {links.map((link, index) => <li key={link} className={index === 0 ? 'is-active' : undefined}><a href="#">{link}</a></li>)}
            </ul>
            <a href="#" className="vju-category-aside-contact">☎ &nbsp; Contact Us</a>
        </aside>
    );
}

function TitleHeader({ content }: { content: ContentFull }) {
    const { breadcrumbs = [] } = useShared<PageProps>();
    return (
        <div className="container-site pt-6">
            <Breadcrumb items={breadcrumbs} />
            <h1 className="mt-4 text-3xl font-bold text-primary-900 sm:text-4xl">{content.title}</h1>
            {content.excerpt && <p className="mt-3 max-w-3xl text-lg text-muted">{content.excerpt}</p>}
        </div>
    );
}

function ContactCard() {
    const { site } = useShared();
    const t = useT();
    const { phone, email, address, map_url } = site.contact ?? {};
    if (!phone && !email && !address) return null;
    const row = 'flex gap-3';
    const icon = 'mt-0.5 shrink-0 text-accent-600';

    return (
        <aside aria-labelledby="contact-card-h" className="rounded-lg border border-line bg-surface p-6">
            <h2 id="contact-card-h" className="text-xl font-bold text-primary-900">
                {t('contact')}
            </h2>
            <p className="mt-1 font-semibold text-ink">{site.name}</p>
            <address className="mt-4 space-y-3 not-italic">
                {address && (
                    <p className={row}>
                        <PinIcon className={icon} />
                        {map_url ? (
                            <a href={map_url} target="_blank" rel="noopener noreferrer" className="text-primary-700 underline-offset-2 hover:underline">
                                {address}
                            </a>
                        ) : (
                            <span>{address}</span>
                        )}
                    </p>
                )}
                {phone && (
                    <p className={row}>
                        <PhoneIcon className={icon} />
                        <a href={`tel:${phone.replace(/[^\d+]/g, '')}`} className="text-primary-700 hover:underline">
                            {phone}
                        </a>
                    </p>
                )}
                {email && (
                    <p className={row}>
                        <MailIcon className={icon} />
                        <a href={`mailto:${email}`} className="break-all text-primary-700 hover:underline">
                            {email}
                        </a>
                    </p>
                )}
            </address>
        </aside>
    );
}

function LegacyContactMaps() {
    const maps = [
        {
            label: 'Cơ sở Mỹ Đình',
            src: 'https://maps.google.com/maps?q=Vietnam%20Japan%20University%20(VJU)%20M%E1%BB%B9%20%C4%90%C3%ACnh%2C%20Ph%E1%BB%91%20L%C6%B0u%20H%E1%BB%AFu%20Ph%C6%B0%E1%BB%9Bc%2C%20M%E1%BB%B9%20%C4%90%C3%ACnh%201%2C%20C%E1%BA%A7u%20Di%E1%BB%85n%2C%20Nam%20T%E1%BB%AB%20Li%C3%AAm%2C%20H%C3%A0%20N%E1%BB%99i&t=m&z=15&output=embed&iwloc=near',
        },
        {
            label: 'Cơ sở Hòa Lạc',
            src: 'https://maps.google.com/maps?q=2F3R%2B689%2C%20Th%E1%BA%A1ch%20Ho%C3%A0%2C%20Th%E1%BA%A1ch%20Th%E1%BA%A5t%2C%20H%C3%A0%20N%E1%BB%99i%2C%20Vietnam&t=m&z=15&output=embed&iwloc=near',
        },
    ];

    return (
        <div className="vju-legacy-contact-maps">
            {maps.map((map) => (
                <figure key={map.label}>
                    <figcaption>{map.label}</figcaption>
                    <iframe src={map.src} title={map.label} loading="lazy" />
                </figure>
            ))}
        </div>
    );
}

export default function Page() {
    const { content, breadcrumbs = [] } = useShared<PageProps>();
    const t = useT();
    const blocks = content.blocks ?? [];
    const template = content.template ?? 'default';
    const legacyHero = LEGACY_PAGE_HEROES[content.url ?? ''];
    const categoryHero = LEGACY_CATEGORY_HEROES[content.url ?? ''];
    const isExamNotices = content.url === '/khao-thi/thong-bao/';
    const isGraduateLookup = content.url === '/collaboration-vn/tra-cuu-thong-tin-tot-nghiep/';
    const isDocumentsPage = content.url === '/tai-lieu-va-huong-dan-2/';
    const isStaffLegacyPage = content.url === '/trang-chu/dao-tao/gioi-thieu/';

    if (template === 'landing') {
        const heroIsTitle = startsWithHero(blocks);
        return (
            <PublicLayout>
                {!heroIsTitle && <h1 className="sr-only">{content.title}</h1>}
                <BlockRenderer blocks={blocks} heroIsTitle={heroIsTitle} />
            </PublicLayout>
        );
    }

    if (Object.hasOwn(BANDS, template)) {
        return (
            <PublicLayout>
                <div className={`relative isolate overflow-hidden bg-gradient-to-br text-white ${BANDS[template]}`}>
                    {content.image && (
                        <div aria-hidden="true" className="absolute inset-0 -z-10 opacity-20 mix-blend-luminosity">
                            <Picture image={content.image} priority decorative className="h-full w-full object-cover" />
                        </div>
                    )}
                    <div className="container-site py-10 sm:py-16">
                        <Breadcrumb items={breadcrumbs} invert />
                        <h1 className="mt-4 max-w-4xl text-3xl font-extrabold leading-tight sm:text-5xl">{content.title}</h1>
                        {content.excerpt && <p className="mt-4 max-w-3xl text-lg text-white/90">{content.excerpt}</p>}
                    </div>
                </div>
                <SectionNav blocks={blocks} />
                <LegacyBody html={content.body} />
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (template === 'contact') {
        return (
            <PublicLayout>
                <TitleHeader content={content} />
                <div className="container-site grid gap-10 py-10 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {content.body && <div className="prose-content" dangerouslySetInnerHTML={{ __html: content.body }} />}
                    </div>
                    <ContactCard />
                </div>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (isGraduateLookup && legacyHero) {
        const graduateInstructions = content.body
            .replace(/^\s*(?:<h2\b[^>]*>[\s\S]*?<\/h2>\s*){2}/i, '')
            .replace(/^\s*<h5\b[^>]*>[\s\S]*?<\/h5>\s*/i, '');

        return (
            <PublicLayout>
                <LegacyPageHero title={content.title} image={legacyHero} breadcrumbs={breadcrumbs} />
                <main className="container-site vju-graduate-page">
                    <section className="vju-graduate-layout">
                        <article className="vju-graduate-content">
                            <h2>Hệ thống tra cứu thông tin tốt nghiệp</h2>
                            <p className="vju-graduate-kicker">TRA CỨU THÔNG TIN NGƯỜI HỌC ĐƯỢC CÔNG NHẬN VÀ CẤP BẰNG TỐT NGHIỆP</p>
                            <div
                                className="prose-content vju-graduate-instructions"
                                dangerouslySetInnerHTML={{ __html: graduateInstructions }}
                            />
                        </article>
                        <form className="vju-graduate-form" onSubmit={(event) => event.preventDefault()}>
                            <div className="vju-graduate-form-heading">
                                <h2>Tra cứu thông tin</h2>
                                <p>Vui lòng nhập đầy đủ thông tin để tra cứu văn bằng.</p>
                            </div>
                            <label>
                                Loại văn bằng
                                <select name="degree_type" defaultValue="">
                                    <option value="" disabled>Chọn loại văn bằng</option>
                                    <option value="dai-hoc">Đại học</option>
                                    <option value="thac-si">Thạc sĩ</option>
                                    <option value="tien-si">Tiến sĩ</option>
                                </select>
                            </label>
                            <label>
                                Năm cấp bằng
                                <select name="issue_year" defaultValue="">
                                    <option value="" disabled>Chọn năm cấp bằng</option>
                                    {Array.from({ length: 8 }, (_, index) => 2018 + index).map((year) => <option key={year} value={year}>{year}</option>)}
                                </select>
                            </label>
                            <label>
                                Số vào sổ
                                <input name="register_number" type="text" placeholder="Nhập số vào sổ" />
                            </label>
                            <label>
                                Số hiệu văn bằng
                                <input name="diploma_number" type="text" placeholder="Nhập số hiệu văn bằng" />
                            </label>
                            <label>
                                Ngày cấp
                                <input name="issue_date" type="text" placeholder="dd/mm/yyyy" inputMode="numeric" />
                            </label>
                            <label>
                                Số định danh cá nhân
                                <input name="personal_id" type="text" placeholder="Nhập số định danh cá nhân" inputMode="numeric" />
                            </label>
                            <button type="submit">Tìm kiếm</button>
                        </form>
                    </section>
                </main>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (isDocumentsPage && legacyHero) {
        return (
            <PublicLayout>
                <LegacyPageHero title={content.title} image={legacyHero} breadcrumbs={breadcrumbs} />
                <main className="container-site vju-documents-page">
                    <LegacyBody html={content.body} />
                </main>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (legacyHero && isStaffLegacyPage) {
        return (
            <PublicLayout>
                <LegacyPageHero title={content.title} image={legacyHero} breadcrumbs={breadcrumbs} />
                <div className="container-site vju-staff-page">
                    <LegacyBody html={content.body} />
                </div>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (categoryHero) {
        const isResearch = content.url === '/nghien-cuu/thong-tin-nhanh/';
        return (
            <PublicLayout>
                <LegacyPageHero title={content.title} image={categoryHero} breadcrumbs={breadcrumbs} category />
                <div className={`container-site vju-category-page ${isResearch ? 'vju-category-page-research' : ''}`}>
                    <LegacyCategoryAside research={isResearch} />
                    <article className="vju-category-page-content">
                        <LegacyBody html={content.body} />
                    </article>
                </div>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    if (isExamNotices) {
        return (
            <PublicLayout>
                <div className="container-site vju-category-page vju-category-page-document">
                    <article className="vju-category-page-content">
                        <Breadcrumb items={breadcrumbs} />
                        <LegacyBody html={content.body} />
                    </article>
                </div>
                <BlockRenderer blocks={blocks} />
            </PublicLayout>
        );
    }

    const pageLinks = blocks
        .map((block, i) => ({ id: blockAnchor(block, i), label: blockHeading(block), type: block.type }))
        .filter((item) => item.label && item.type !== 'hero');
    const isLegacyContact = /contact|liên hệ/i.test(content.title) && /bản đồ google|google map/i.test(content.body);

    return (
        <PublicLayout>
            {content.image && (
                <div className="vju-inner-cover">
                    <Picture image={content.image} priority decorative className="h-full w-full object-cover" />
                </div>
            )}
            <div className={`container-site vju-inner-layout ${pageLinks.length > 1 ? 'vju-inner-layout-with-aside' : ''}`}>
                {pageLinks.length > 1 && (
                    <aside className="vju-inner-aside" aria-label={t('on_this_page')}>
                        <ul>
                            {pageLinks.map((item) => <li key={item.id}><a href={`#${item.id}`}>{item.label}</a></li>)}
                        </ul>
                    </aside>
                )}
                <article className="vju-inner-article">
                    <Breadcrumb items={breadcrumbs} />
                    <h1 className="vju-content-title mt-4">{content.title}</h1>
                    {content.excerpt && <p className="mt-3 max-w-3xl text-lg text-muted">{content.excerpt}</p>}
                    <LegacyBody html={content.body} />
                    {isLegacyContact && <LegacyContactMaps />}
                    <BlockRenderer blocks={blocks} />
                </article>
            </div>
        </PublicLayout>
    );
}
