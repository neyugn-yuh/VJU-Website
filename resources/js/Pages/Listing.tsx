import { Link } from '@inertiajs/react';
import Pagination from '@/Components/Pagination/Pagination';
import PostCard from '@/Components/PostCard/PostCard';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import ListingLayout from '@/Layouts/ListingLayout';
import type { ContentTypeKey, ListingProps } from '@/Types';

/** Archive types shown as a compact "date + download" list instead of image cards. */
const COMPACT: ContentTypeKey[] = ['document', 'notification', 'tuition_fee'];

export default function Listing() {
    const { title, description, type, items = [], pagination, subcategories = [], breadcrumbs = [] } = useShared<ListingProps>();
    const t = useT();
    const compact = type !== null && COMPACT.includes(type);

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
        <ListingLayout title={title} description={description} breadcrumbs={breadcrumbs} header={chips}>
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
                <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, i) => (
                        <li key={item.id}>
                            <PostCard item={item} headingLevel="h2" priority={i === 0} />
                        </li>
                    ))}
                </ul>
            )}
            <Pagination pagination={pagination} />
        </ListingLayout>
    );
}
