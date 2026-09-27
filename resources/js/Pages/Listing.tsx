import { Link } from '@inertiajs/react';
import Breadcrumb from '@/Components/Breadcrumb/Breadcrumb';
import Picture from '@/Components/Media/Picture';
import Pagination from '@/Components/Pagination/Pagination';
import PostCard from '@/Components/PostCard/PostCard';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import ListingLayout from '@/Layouts/ListingLayout';
import PublicLayout from '@/Layouts/PublicLayout';
import type { ContentTypeKey, ListingProps } from '@/Types';

/** Archive types shown as a compact "date + download" list instead of image cards. */
const COMPACT: ContentTypeKey[] = ['document', 'notification', 'tuition_fee'];

function LegacyCategoryArchive({ title, breadcrumbs, items, pagination }: Pick<ListingProps, 'title' | 'breadcrumbs' | 'items' | 'pagination'>) {
    return (
        <PublicLayout>
            <div className="container-site vju-category-archive">
                <Breadcrumb items={breadcrumbs} />
                <h1>Danh mục: {title}</h1>
                <div className="vju-category-archive-list">
                    {items.map((item) => (
                        <article key={item.id} className="vju-category-archive-item">
                            <Link href={item.url} className="vju-category-archive-title">{item.title}</Link>
                            {item.image && <Link href={item.url} className="vju-category-archive-image"><Picture image={item.image} priority={item.id === items[0]?.id} decorative /></Link>}
                            {item.excerpt && <p>{item.excerpt}</p>}
                        </article>
                    ))}
                </div>
                <Pagination pagination={pagination} />
            </div>
        </PublicLayout>
    );
}

export default function Listing() {
    const { title, description, type, items = [], pagination, subcategories = [], breadcrumbs = [] } = useShared<ListingProps>();
    const t = useT();
    const compact = type !== null && COMPACT.includes(type);
    const legacyCategoryArchive = typeof window !== 'undefined' && window.location.pathname.startsWith('/news-vn/');

    if (legacyCategoryArchive) {
        return <LegacyCategoryArchive title={title} breadcrumbs={breadcrumbs} items={items} pagination={pagination} />;
    }

    const chips =
        subcategories.length > 0 ? (
            <nav aria-label={t('subcategories')}>
                <ul className="flex flex-wrap gap-2">
                    {subcategories.map((c) => (
                        <li key={c.url}>
                            <Link
                                href={c.url}
                                className="inline-block rounded-full border border-primary-200 bg-white px-4 py-1.5 text-sm font-medium text-primary-800 hover:border-primary-700 hover:bg-primary-700 hover:text-white"
                            >
                                {c.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        ) : null;

    return (
        <ListingLayout title={title} description={description} breadcrumbs={breadcrumbs} header={chips} heroImage={items[0]?.image}>
            {items.length === 0 ? (
                <p className="rounded-lg bg-surface p-8 text-center text-muted">{t('no_results')}</p>
            ) : compact ? (
                <ul className="mx-auto max-w-4xl border-t border-line">
                    {items.map((item) => (
                        <li key={item.id}>
                            <PostCard item={item} variant="compact" headingLevel="h2" />
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="vju-listing-featured">
                    <div className="vju-listing-lead">
                        <PostCard item={items[0]} variant="featured" headingLevel="h2" priority />
                    </div>
                    {items.length > 1 && (
                        <ul className="vju-listing-side">
                            {items.slice(1).map((item) => (
                                <li key={item.id}><PostCard item={item} variant="list" headingLevel="h2" /></li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            <Pagination pagination={pagination} />
        </ListingLayout>
    );
}
