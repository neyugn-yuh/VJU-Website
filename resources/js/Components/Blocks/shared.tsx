import type { ReactNode } from 'react';
import type { MediaImage } from '@/Types';
import SmartLink from '../Navigation/SmartLink';

export interface BlockProps<T> {
    data: T;
    /** Anchor id (in-page navigation). */
    id: string;
    /** First block of the page: the hero may be the page <h1> and its image the LCP. */
    first: boolean;
    /** Render the hero heading as <h1> (page has no other title). */
    asTitle: boolean;
}

export type ButtonData = { label?: string; url?: string };
export type ImageData = { image?: MediaImage | null };

export const str = (v: unknown): string => (typeof v === 'string' ? v : '');
export const list = <T,>(v: unknown): T[] => (Array.isArray(v) ? (v as T[]) : []);

export function Section({ id, heading, intro, children, className = '', action }: {
    id: string;
    heading?: string;
    intro?: string;
    children: ReactNode;
    className?: string;
    action?: ReactNode;
}) {
    return (
        <section id={id} aria-labelledby={heading ? `${id}-h` : undefined} className={`scroll-mt-24 py-10 sm:py-14 ${className}`}>
            <div className="container-site">
                {(heading || action) && (
                    <div className="vju-section-heading mb-6 flex flex-wrap items-end justify-between gap-4 sm:mb-8">
                        {heading && (
                            <h2 id={`${id}-h`} className="relative pb-3 text-2xl font-bold text-primary-900 after:absolute after:bottom-0 after:left-0 after:h-1 after:w-12 after:rounded after:bg-accent-600 sm:text-3xl">
                                {heading}
                            </h2>
                        )}
                        {action}
                    </div>
                )}
                {intro && <p className="-mt-2 mb-8 max-w-3xl text-muted">{intro}</p>}
                {children}
            </div>
        </section>
    );
}

export function Buttons({ buttons, dark = false }: { buttons: ButtonData[]; dark?: boolean }) {
    const valid = buttons.filter((b) => b.label && b.url);
    if (!valid.length) return null;
    return (
        <div className="flex flex-wrap gap-3">
            {valid.map((b, i) => (
                <SmartLink
                    key={i}
                    href={b.url as string}
                    className={
                        i === 0
                            ? 'inline-flex items-center rounded-md bg-accent-600 px-5 py-3 font-semibold text-white shadow hover:bg-accent-700'
                            : `inline-flex items-center rounded-md border-2 px-5 py-2.5 font-semibold ${
                                  dark ? 'border-white text-white hover:bg-white hover:text-primary-900' : 'border-primary-700 text-primary-700 hover:bg-primary-700 hover:text-white'
                              }`
                    }
                >
                    {b.label}
                </SmartLink>
            ))}
        </div>
    );
}
