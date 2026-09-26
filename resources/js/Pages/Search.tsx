import Pagination from '@/Components/Pagination/Pagination';
import PostCard from '@/Components/PostCard/PostCard';
import SearchForm from '@/Components/Search/SearchForm';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import ListingLayout from '@/Layouts/ListingLayout';
import type { SearchProps } from '@/Types';

export default function Search() {
    const { q = '', items = [], pagination } = useShared<SearchProps>();
    const t = useT();
    const searched = q.length >= 2;

    return (
        <ListingLayout
            title={t('search_results')}
            header={
                <div className="max-w-2xl">
                    {/* key: remount so the input shows the new q after a client-side visit */}
                    <SearchForm key={q} q={q} size="lg" />
                </div>
            }
        >
            {searched && (
                <div className="mx-auto max-w-4xl">
                    <p role="status" className="mb-6 text-muted">
                        {items.length > 0 ? t('results_count', { count: pagination?.total ?? items.length }) : ''}
                    </p>
                    {items.length > 0 ? (
                        <ul className="space-y-3">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <PostCard item={item} variant="list" headingLevel="h2" />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="rounded-lg bg-surface p-8 text-center text-muted">{t('no_results')}</p>
                    )}
                    <Pagination pagination={pagination} />
                </div>
            )}
        </ListingLayout>
    );
}
