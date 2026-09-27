import { Link } from '@inertiajs/react';
import { Section } from '@/Components/Blocks/shared';
import PostCard from '@/Components/PostCard/PostCard';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import PublicLayout from '@/Layouts/PublicLayout';
import type { ErrorProps } from '@/Types';

export default function ErrorPage() {
    const { status = 500, latest = [], locale, locales } = useShared<ErrorProps>();
    const t = useT();
    const missing = status === 404 || status === 410;
    const home = locales?.find((l) => l.code === locale)?.home ?? '/';

    return (
        <PublicLayout>
            <div className="container-site py-16 text-center sm:py-24">
                <p className="text-7xl font-extrabold text-primary-700 sm:text-8xl" aria-hidden="true">
                    {status}
                </p>
                <h1 className="mt-4 text-2xl font-bold text-primary-900 sm:text-3xl">
                    <span className="sr-only">{status} – </span>
                    {missing ? t('not_found') : t('error')}
                </h1>
                {missing && <p className="mx-auto mt-3 max-w-xl text-muted">{t('not_found_text')}</p>}
                <Link prefetch="hover" viewTransition href={home} className="mt-8 inline-flex rounded-md bg-primary-700 px-6 py-3 font-semibold text-white hover:bg-primary-800">
                    {t('back_home')}
                </Link>
            </div>
            {latest.length > 0 && (
                <Section id="latest-news" heading={t('latest_news')} className="border-t border-line bg-surface">
                    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {latest.map((item) => (
                            <li key={item.id}>
                                <PostCard item={item} />
                            </li>
                        ))}
                    </ul>
                </Section>
            )}
        </PublicLayout>
    );
}
