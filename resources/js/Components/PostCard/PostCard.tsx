import { Link } from '@inertiajs/react';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import type { ContentCard } from '@/Types';
import { fileKind, formatDate } from '@/Utils/format';
import Picture from '../Media/Picture';
import { CalendarIcon, DownloadIcon } from '../Navigation/Icons';

interface Props {
    item: ContentCard;
    variant?: 'card' | 'list' | 'compact' | 'featured';
    headingLevel?: 'h2' | 'h3';
    priority?: boolean;
}

function Meta({ item }: { item: ContentCard }) {
    const { locale } = useShared();
    return (
        <div className="relative z-10 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
            {item.category && (
                <Link href={item.category.url} className="rounded-full bg-accent-50 px-2.5 py-0.5 font-semibold text-accent-700 hover:bg-accent-100">
                    {item.category.name}
                </Link>
            )}
            {item.date && (
                <time dateTime={item.date} className="inline-flex items-center gap-1">
                    <CalendarIcon width={14} height={14} />
                    {formatDate(item.date, locale)}
                </time>
            )}
        </div>
    );
}

/** Title link stretched over the whole card (::after) so the card is one click target. */
function Title({ item, as: H, className }: { item: ContentCard; as: 'h2' | 'h3'; className: string }) {
    return (
        <H className={className}>
            <Link href={item.url} className="after:absolute after:inset-0 hover:text-primary-700 focus-visible:after:rounded-lg">
                {item.title}
            </Link>
        </H>
    );
}

export default function PostCard({ item, variant = 'card', headingLevel = 'h3', priority = false }: Props) {
    const t = useT();
    const { locale } = useShared();

    if (variant === 'compact') {
        const file = item.fields?.file;
        const date = item.fields?.issued_at ?? item.date;
        const H = headingLevel;
        return (
            <article className="flex flex-col gap-3 border-b border-line py-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <H className="font-semibold text-ink">
                        <Link href={item.url} className="hover:text-primary-700 hover:underline">
                            {item.title}
                        </Link>
                    </H>
                    <p className="mt-1 flex flex-wrap gap-x-3 text-sm text-muted">
                        {date && <time dateTime={date}>{formatDate(date, locale)}</time>}
                        {item.fields?.number && (
                            <span>
                                {t('number')}: {item.fields.number}
                            </span>
                        )}
                    </p>
                </div>
                {file && (
                    <a
                        href={file.url}
                        className="inline-flex shrink-0 items-center gap-2 self-start rounded-md border border-primary-700 px-3 py-1.5 text-sm font-semibold text-primary-700 hover:bg-primary-700 hover:text-white sm:self-center"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <DownloadIcon width={16} height={16} />
                        {t('download')}
                        {(file.size || file.mime) && (
                            <span className="font-normal opacity-80">({[fileKind(file.mime, file.url), file.size].filter(Boolean).join(', ')})</span>
                        )}
                    </a>
                )}
            </article>
        );
    }

    if (variant === 'list') {
        return (
            <article className="relative flex gap-4 rounded-lg p-2 transition hover:bg-surface">
                {item.image && (
                    <div className="w-28 shrink-0 overflow-hidden rounded-md sm:w-40">
                        <Picture image={item.image} thumb className="aspect-[4/3] h-full w-full object-cover" decorative />
                    </div>
                )}
                <div className="min-w-0 space-y-1.5">
                    <Meta item={item} />
                    <Title item={item} as={headingLevel} className="line-clamp-3 font-semibold leading-snug text-ink" />
                    {item.excerpt && <p className="line-clamp-2 hidden text-sm text-muted sm:block">{item.excerpt}</p>}
                </div>
            </article>
        );
    }

    const featured = variant === 'featured';
    return (
        <article className="group relative flex h-full flex-col overflow-hidden rounded-lg border border-line bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md motion-reduce:hover:translate-y-0">
            <div className="aspect-[16/9] overflow-hidden bg-primary-50">
                {item.image ? (
                    <Picture
                        image={item.image}
                        thumb={!featured}
                        priority={priority}
                        decorative
                        className="h-full w-full object-cover transition duration-300 group-hover:scale-105 motion-reduce:group-hover:scale-100"
                    />
                ) : (
                    <div className="h-full w-full bg-gradient-to-br from-primary-700 to-primary-900" aria-hidden="true" />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-2 p-4 sm:p-5">
                <Meta item={item} />
                <Title item={item} as={headingLevel} className={`font-bold leading-snug text-ink ${featured ? 'text-xl sm:text-2xl' : 'line-clamp-3 text-lg'}`} />
                {item.excerpt && <p className={`text-sm text-muted ${featured ? 'line-clamp-4' : 'line-clamp-3'}`}>{item.excerpt}</p>}
            </div>
        </article>
    );
}
