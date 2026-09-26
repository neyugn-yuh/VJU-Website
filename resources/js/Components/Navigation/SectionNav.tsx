import { useT } from '@/Hooks/useT';
import type { Block } from '@/Types';
import { blockAnchor, blockHeading } from '@/Utils/anchors';

/** In-page navigation built from the headings of the page's content modules. */
export default function SectionNav({ blocks }: { blocks: Block[] }) {
    const t = useT();
    const links = blocks
        .map((block, i) => ({ id: blockAnchor(block, i), label: blockHeading(block), type: block.type }))
        .filter((l) => l.label && l.type !== 'hero');
    if (links.length < 2) return null;

    return (
        <nav aria-label={t('on_this_page')} className="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur">
            <div className="container-site">
                <ul className="-mx-1 flex gap-1 overflow-x-auto py-2 [scrollbar-width:thin]">
                    {links.map((l) => (
                        <li key={l.id} className="shrink-0">
                            <a href={`#${l.id}`} className="block rounded-full px-3 py-1.5 text-sm font-medium text-primary-800 hover:bg-primary-50">
                                {l.label}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </nav>
    );
}
