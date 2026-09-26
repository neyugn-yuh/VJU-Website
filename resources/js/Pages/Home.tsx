import BlockRenderer, { startsWithHero } from '@/Components/Blocks/BlockRenderer';
import { Section } from '@/Components/Blocks/shared';
import PostCard from '@/Components/PostCard/PostCard';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import PublicLayout from '@/Layouts/PublicLayout';
import type { HomeProps } from '@/Types';

export default function Home() {
    const { blocks = [], latest = [], site } = useShared<HomeProps>();
    const t = useT();
    const heroIsTitle = startsWithHero(blocks);

    return (
        <PublicLayout>
            {!heroIsTitle && <h1 className="sr-only">{site.name}</h1>}
            {blocks.length > 0 ? (
                <BlockRenderer blocks={blocks} heroIsTitle={heroIsTitle} />
            ) : (
                latest.length > 0 && (
                    <Section id="latest-news" heading={t('latest_news')}>
                        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {latest.map((item, i) => (
                                <li key={item.id}>
                                    <PostCard item={item} priority={i === 0} />
                                </li>
                            ))}
                        </ul>
                    </Section>
                )
            )}
        </PublicLayout>
    );
}
