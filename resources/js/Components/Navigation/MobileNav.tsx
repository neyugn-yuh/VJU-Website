import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { useT } from '@/Hooks/useT';
import type { MenuNode } from '@/Types';
import { ChevronDown, CloseIcon, MenuIcon } from './Icons';
import SmartLink from './SmartLink';

function Item({ node, depth }: { node: MenuNode; depth: number }) {
    const [open, setOpen] = useState(false);
    const id = useId();
    const hasChildren = node.children.length > 0;

    return (
        <li>
            <div className="flex items-stretch border-b border-line/70">
                <SmartLink
                    href={node.url}
                    target={node.target}
                    className="flex-1 py-3 pr-2 text-ink hover:text-primary-700"
                    style={{ paddingLeft: `${1 + depth}rem` }}
                >
                    <span className={depth === 0 ? 'font-semibold' : 'text-sm'}>{node.label}</span>
                </SmartLink>
                {hasChildren && (
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls={id}
                        aria-label={node.label}
                        onClick={() => setOpen(!open)}
                        className="flex w-12 items-center justify-center border-l border-line/70 text-primary-700"
                    >
                        <ChevronDown width={18} height={18} className={`transition ${open ? 'rotate-180' : ''}`} />
                    </button>
                )}
            </div>
            {hasChildren && (
                <ul id={id} hidden={!open} className="bg-surface">
                    {node.children.map((child, i) => (
                        <Item key={i} node={child} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

/** Off-canvas drawer (< lg): focus moves in, Tab is trapped, Escape/close returns focus to the toggle. */
export default function MobileNav({ items, children }: { items: MenuNode[]; children?: ReactNode }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const toggle = useRef<HTMLButtonElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const id = useId();

    // Close when an Inertia visit starts (a link inside the drawer was followed).
    useEffect(() => router.on('start', () => setOpen(false)), []);

    useEffect(() => {
        if (!open) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panel.current?.querySelector<HTMLElement>('button')?.focus();

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
                return;
            }
            if (e.key !== 'Tab' || !panel.current) return;
            const focusable = [...panel.current.querySelectorAll<HTMLElement>('a[href], button, input')].filter((el) => !el.closest('[hidden]'));
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last?.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first?.focus();
            }
        };
        document.addEventListener('keydown', onKey);
        const button = toggle.current;

        return () => {
            document.body.style.overflow = previous;
            document.removeEventListener('keydown', onKey);
            button?.focus({ preventScroll: true });
        };
    }, [open]);

    return (
        <div className="vju-mobile-nav lg:hidden">
            <button
                ref={toggle}
                type="button"
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-semibold text-primary-900"
            >
                <MenuIcon />
                <span>{t('menu')}</span>
            </button>

            <div id={id} hidden={!open}>
                <div className="fixed inset-0 z-50 bg-primary-950/60" aria-hidden="true" onClick={() => setOpen(false)} />
                <div
                    ref={panel}
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('menu')}
                    className="fixed inset-y-0 right-0 z-50 flex w-[88vw] max-w-sm flex-col overflow-y-auto bg-white shadow-xl"
                >
                    <div className="flex items-center justify-between border-b border-line px-4 py-3">
                        <span className="font-bold text-primary-900">{t('menu')}</span>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="inline-flex items-center gap-1 rounded-md p-2 text-sm text-muted hover:text-ink"
                        >
                            <CloseIcon />
                            <span>{t('close')}</span>
                        </button>
                    </div>
                    {children && <div className="space-y-4 border-b border-line p-4">{children}</div>}
                    {items.length > 0 && (
                        <nav aria-label={t('menu')}>
                            <ul>
                                {items.map((node, i) => (
                                    <Item key={i} node={node} depth={0} />
                                ))}
                            </ul>
                        </nav>
                    )}
                </div>
            </div>
        </div>
    );
}
