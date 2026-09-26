import type { ComponentType } from 'react';
import type { Block } from '@/Types';
import { blockAnchor } from '@/Utils/anchors';
import { Cards, Cta, Documents, Faq, Logos, PostList, RichText, Stats, Steps, Video } from './ContentBlocks';
import Hero from './Hero';
import type { BlockProps } from './shared';

// Keys match app/Filament/Forms/PageBlocks.php. Unknown types render nothing.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const BLOCKS: Record<string, ComponentType<BlockProps<any>>> = {
    hero: Hero,
    rich_text: RichText,
    cards: Cards,
    stats: Stats,
    post_list: PostList,
    steps: Steps,
    faq: Faq,
    documents: Documents,
    cta: Cta,
    logos: Logos,
    video: Video,
};

/** `heroIsTitle`: the page has no other <h1>, so a leading hero heading becomes it. */
export default function BlockRenderer({ blocks, heroIsTitle = false }: { blocks: Block[]; heroIsTitle?: boolean }) {
    return (
        <>
            {blocks.map((block, i) => {
                const Component = BLOCKS[block.type];
                return Component ? (
                    <Component key={i} data={block.data ?? {}} id={blockAnchor(block, i)} first={i === 0} asTitle={heroIsTitle && i === 0} />
                ) : null;
            })}
        </>
    );
}

export const startsWithHero = (blocks: Block[]) => blocks[0]?.type === 'hero';
