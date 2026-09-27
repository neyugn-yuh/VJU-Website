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
    variant: 'default' | 'program-directory' | 'news-directory' | 'document-directory';
}

/**
 * A few migrated directory pages still contain the old WordPress markup:
 * section headings followed by image paragraphs and linked headings. Group
 * those pairs so the page can use the same responsive cards as the rest of
 * the site while leaving ordinary article HTML untouched.
 */
function formatLegacyBody(html: string): FormattedBody {
    const imageHeadingPair = /(<p\b[^>]*>[\s\S]*?<img\b[^>]*\/?\s*>[\s\S]*?<\/p>)\s*(<h[234]\b[^>]*>[\s\S]*?<\/h[234]>)/gi;
    const pairs = html.match(imageHeadingPair);
    const hasNewsFeature = /<p\b[^>]*>[\s\S]*?<img\b[\s\S]*?<\/p>\s*<h[34]\b[\s\S]*?<\/h[34]>\s*<p\b[\s\S]*?<\/p>\s*<p\b[\s\S]*?<\/p>/i.test(html);
    const hasNewsItems = /<ul\b[^>]*>[\s\S]*?<li\b[\s\S]*?<img\b[\s\S]*?<\/li>[\s\S]*?<\/ul>/i.test(html);
    const downloadCount = (html.match(/>\s*download\s*</gi) ?? []).length;

    if (hasNewsFeature && hasNewsItems) {
        // The old news page stores the featured item and the side list as
        // adjacent paragraphs/list markup. Restore the two-column structure
        // used by the crawled Elementor page without changing the content.
        const feature = /(<p\b[^>]*>[\s\S]*?<img\b[\s\S]*?<\/p>\s*<h[34]\b[\s\S]*?<\/h[34]>\s*<p\b[\s\S]*?<\/p>\s*<p\b[\s\S]*?<\/p>)/i;
        const sideList = /(<ul\b[^>]*>[\s\S]*?<\/ul>)/i;
        return {
            html: html
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
