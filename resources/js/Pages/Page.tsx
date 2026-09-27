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
    isProgramDirectory: boolean;
}

/**
 * A few migrated directory pages still contain the old WordPress markup:
 * section headings followed by image paragraphs and linked headings. Group
 * those pairs so the page can use the same responsive cards as the rest of
 * the site while leaving ordinary article HTML untouched.
 */
function formatLegacyBody(html: string): FormattedBody {
    const imageHeadingPair = /(<p\b[^>]*>[\s\S]*?<img\b[^>]*\/?\s*>[\s\S]*?<\/p>)\s*(<h[23]\b[^>]*>[\s\S]*?<\/h[23]>)/gi;
    const pairs = html.match(imageHeadingPair);

    if (!/^\s*<h2\b/i.test(html) || !pairs || pairs.length < 3) {
        return { html, isProgramDirectory: false };
    }

    return {
        html: html.replace(imageHeadingPair, '<div class="vju-program-card">$1$2</div>'),
        isProgramDirectory: true,
    };
}

function LegacyBody({ html }: { html: string }) {
    if (!html) return null;

    const formatted = formatLegacyBody(html);
    return (
        <div
            className={`prose-content mt-8 ${formatted.isProgramDirectory ? 'vju-program-directory' : ''}`}
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
                    <BlockRenderer blocks={blocks} />
                </article>
            </div>
        </PublicLayout>
    );
}
