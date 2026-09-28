import { useRef, useState, type FocusEvent, type KeyboardEvent } from 'react';
import { useT } from '@/Hooks/useT';
import type { MenuNode } from '@/Types';
import { CaretDown, ChevronRight } from './Icons';
import SmartLink from './SmartLink';

/**
 * Main navigation (>= lg). Submenus open on hover and when focus enters the item;
 * the chevron button toggles them (touch / screen readers) and Escape closes.
 */
function Item({ node, depth }: { node: MenuNode; depth: number }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const button = useRef<HTMLButtonElement>(null);
    const suppressFocusOpen = useRef(false);
    const hasChildren = node.children.length > 0;
    const top = depth === 0;

    const onBlur = (e: FocusEvent<HTMLLIElement>) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
            setOpen(false);
            suppressFocusOpen.current = false;
        }
    };
    const onKeyDown = (e: KeyboardEvent<HTMLLIElement>) => {
        if (e.key === 'Escape' && open) {
            e.stopPropagation();
            suppressFocusOpen.current = true;
            setOpen(false);
            button.current?.focus();
        }
    };

    const linkClass = top
        ? 'flex items-center py-2 pl-3 pr-2 text-base text-primary-900 hover:text-accent-600'
        : 'flex flex-1 items-center px-4 py-2.5 text-sm font-semibold text-ink hover:bg-primary-50 hover:text-primary-700';

    return (
        <li
            className="relative"
            onMouseEnter={hasChildren ? () => setOpen(true) : undefined}
            onMouseLeave={hasChildren ? () => setOpen(false) : undefined}
            onFocus={hasChildren ? () => !suppressFocusOpen.current && setOpen(true) : undefined}
            onBlur={hasChildren ? onBlur : undefined}
            onKeyDown={hasChildren ? onKeyDown : undefined}
        >
            <div className="flex items-center">
                <SmartLink href={node.url} target={node.target} className={linkClass}>
                    {node.label}
                </SmartLink>
                {hasChildren && (
                    <button
                        ref={button}
                        type="button"
                        aria-expanded={open}
                        aria-label={`${t('menu')}: ${node.label}`}
                        onClick={() => {
                            suppressFocusOpen.current = open;
                            setOpen(!open);
                        }}
                        className={`${top ? 'mr-3 p-0.5 text-primary-900' : 'px-3 py-2.5 text-muted'} rounded hover:text-accent-600`}
                    >
                        {top ? (
                            <CaretDown width={14} height={14} />
                        ) : (
                            <ChevronRight width={16} height={16} />
                        )}
                    </button>
                )}
            </div>
            {hasChildren && (
                <ul
                    hidden={!open}
                    className={`absolute z-50 min-w-60 rounded-md border border-line bg-white py-2 shadow-lg ${
                        top ? 'left-0 top-full' : 'left-full top-0 -mt-2'
                    }`}
                >
                    {node.children.map((child, i) => (
                        <Item key={i} node={child} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function DesktopNav({ items }: { items: MenuNode[] }) {
    const t = useT();
    if (!items.length) return null;
    return (
        <nav aria-label={t('menu')} className="hidden lg:block">
            <ul className="flex flex-wrap items-center gap-x-1">
                {items.map((node, i) => (
                    <Item key={i} node={node} depth={0} />
                ))}
            </ul>
        </nav>
    );
}
