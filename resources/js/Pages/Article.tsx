import { Link } from '@inertiajs/react';
import { ArticleMeta, Comments, FieldsPanel, ShareLinks, TagList } from '@/Components/Article/ArticleParts';
import BlockRenderer from '@/Components/Blocks/BlockRenderer';
import { Section } from '@/Components/Blocks/shared';
import Picture from '@/Components/Media/Picture';
import PostCard from '@/Components/PostCard/PostCard';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import ArticleLayout from '@/Layouts/ArticleLayout';
import type { ArticleProps } from '@/Types';

/**
 * WordPress stores the featured image in the article body as well as in the
 * post thumbnail. The React article header renders the thumbnail separately,
 * so remove only that leading duplicate while preserving all authored media.
 */
function withoutDuplicateFeaturedImage(body: string, imageUrl?: string | null, originalUrl?: string | null) {
    if (!body || (!imageUrl && !originalUrl)) return body;

    const firstImage = body.match(/^\s*<p\b[^>]*>\s*(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>\s*)?<\/p>\s*/i);
    if (!firstImage) return body;

    const src = firstImage[0].match(/\bsrc=["']([^"']+)["']/i)?.[1] ?? '';
    const basename = (value: string) => value.split(/[/?#]/).pop()?.toLowerCase() ?? '';
    const sourceName = basename(src);
    const matches = [imageUrl, originalUrl].filter(Boolean).some((value) => {
        const expected = basename(value as string);
        return expected !== '' && sourceName === expected;
    });

    return matches ? body.slice(firstImage[0].length) : body;
}

export default function Article() {
    const { content, breadcrumbs = [], related = [], comments = [], preview, seo, t: strings } = useShared<ArticleProps>();
    const t = useT();
    const typeLabel = content.type !== 'post' ? (strings.types?.[content.type] ?? '') : '';
    const body = withoutDuplicateFeaturedImage(content.body, content.image?.url, content.image?.original);

    const relatedGrid =
        related.length > 0 ? (
            <Section id="related" heading={t('related')} className="border-t border-line bg-surface">
                <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {related.map((item) => (
                        <li key={item.id}>
                            <PostCard item={item} />
                        </li>
                    ))}
                </ul>
            </Section>
        ) : null;

    return (
        <ArticleLayout breadcrumbs={breadcrumbs} after={<>{content.blocks.length > 0 && <BlockRenderer blocks={content.blocks} />}{relatedGrid}</>}>
            <article>
                <header>
                    <div className="flex flex-wrap gap-2">
                        {typeLabel && <span className="rounded bg-primary-700 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">{typeLabel}</span>}
                        {content.categories.map((c) => (
                            <Link key={c.url} href={c.url} className="rounded-full bg-accent-50 px-3 py-0.5 text-xs font-semibold text-accent-700 hover:bg-accent-100">
                                {c.name}
                            </Link>
                        ))}
                    </div>
                    <h1 className="mt-3 text-3xl font-bold leading-tight text-primary-900 sm:text-4xl">{content.title}</h1>
                    <ArticleMeta content={content} />
                </header>

                {content.image && (
                    <figure className="mt-8">
                        <Picture image={content.image} priority className="h-auto w-full rounded-lg" />
                        {content.image.caption && <figcaption className="mt-2 text-center text-sm text-muted">{content.image.caption}</figcaption>}
                    </figure>
                )}

                {content.fields && <FieldsPanel fields={content.fields} />}

                {body && <div className="prose-content mt-8" dangerouslySetInnerHTML={{ __html: body }} />}

                <footer className="mt-10 space-y-5 border-t border-line pt-6">
                    <TagList tags={content.tags} />
                    <ShareLinks url={seo?.canonical ?? content.url} title={content.title} />
                </footer>
            </article>

            {content.commentable && !preview && <Comments contentId={content.id} comments={comments} />}
        </ArticleLayout>
    );
}
