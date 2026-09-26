import type { Block } from '@/Types';

export function slugify(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-+|-+$/g, '');
}

export const blockHeading = (block: Block): string => (typeof block.data?.heading === 'string' ? block.data.heading : '');

/** Stable anchor id for a block (used by the in-page section navigation). */
export const blockAnchor = (block: Block, index: number): string => `${slugify(blockHeading(block)) || block.type}-${index + 1}`;
