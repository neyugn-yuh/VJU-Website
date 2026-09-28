import { useState } from 'react';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import type { ContentCard, FileRef, MediaImage } from '@/Types';
import { fileKind } from '@/Utils/format';
import Picture from '../Media/Picture';
import { ChevronDown, ChevronRight, DownloadIcon, PlayIcon } from '../Navigation/Icons';
import SmartLink from '../Navigation/SmartLink';
import PostCard from '../PostCard/PostCard';
import { Buttons, list, Section, str, type BlockProps, type ButtonData } from './shared';

// ---- rich_text -------------------------------------------------------------
export function RichText({ data, id }: BlockProps<{ heading?: string; body?: string }>) {
    return (
        <Section id={id} heading={str(data.heading)}>
            <div className="prose-content max-w-3xl" dangerouslySetInnerHTML={{ __html: str(data.body) }} />
        </Section>
    );
}

// ---- cards -----------------------------------------------------------------
type CardItem = { title?: string; text?: string; image?: MediaImage | null; url?: string };
const COLUMNS: Record<number, string> = {
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-2 lg:grid-cols-3',
    4: 'sm:grid-cols-2 lg:grid-cols-4',
};

export function Cards({ data, id }: BlockProps<{ heading?: string; intro?: string; columns?: number | string; items?: CardItem[] }>) {
    const t = useT();
    const cols = COLUMNS[Number(data.columns)] ?? COLUMNS[3];
    return (
        <Section id={id} heading={str(data.heading)} intro={str(data.intro)} className="vju-cards-section">
            <ul className={`grid gap-6 ${cols}`}>
                {list<CardItem>(data.items).map((item, i) => (
                    <li key={i} className="group relative flex flex-col overflow-hidden rounded-lg border border-line bg-white shadow-sm transition hover:shadow-md">
                        {item.image && (
                            <div className="aspect-[16/10] overflow-hidden">
                                <Picture image={item.image} thumb decorative className="h-full w-full object-cover" />
                            </div>
                        )}
                        <div className="flex flex-1 flex-col gap-2 border-t-4 border-accent-600 p-5">
                            <h3 className="text-lg font-bold text-primary-900">
                                {item.url ? (
                                    <SmartLink href={item.url} className="after:absolute after:inset-0 hover:text-accent-600">
                                        {item.title}
                                    </SmartLink>
                                ) : (
                                    item.title
                                )}
                            </h3>
                            {item.text && <p className="text-sm text-muted">{item.text}</p>}
                            {item.url && (
                                <span aria-hidden="true" className="mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold text-primary-700">
                                    {t('read_more')}
                                    <ChevronRight width={16} height={16} />
                                </span>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

// ---- stats -----------------------------------------------------------------
export function Stats({ data, id }: BlockProps<{ heading?: string; items?: { value?: string; label?: string }[] }>) {
    const items = list<{ value?: string; label?: string }>(data.items);
    return (
        <section id={id} aria-labelledby={data.heading ? `${id}-h` : undefined} className="scroll-mt-24 bg-primary-800 py-12 text-white sm:py-16">
            <div className="container-site">
                {data.heading && (
                    <h2 id={`${id}-h`} className="mb-8 text-center text-2xl font-bold sm:text-3xl">
                        {data.heading}
                    </h2>
                )}
                <dl className="grid grid-cols-2 gap-6 md:grid-cols-[repeat(auto-fit,minmax(10rem,1fr))]">
                    {items.map((item, i) => (
                        <div key={i} className="flex flex-col-reverse items-center text-center">
                            <dt className="mt-1 text-sm text-white/85 sm:text-base">{item.label}</dt>
                            <dd className="text-4xl font-extrabold text-white sm:text-5xl">{item.value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

// ---- post_list -------------------------------------------------------------
type PostListData = { heading?: string; style?: 'grid' | 'list' | 'featured'; items?: ContentCard[]; more_url?: string | null };

export function PostList({ data, id }: BlockProps<PostListData>) {
    const t = useT();
    const items = list<ContentCard>(data.items);
    if (!items.length) return null;
    const more = data.more_url ? (
        <SmartLink href={data.more_url} className="inline-flex items-center gap-1 font-semibold text-primary-700 hover:text-accent-600">
            {t('view_all')}
            <ChevronRight width={16} height={16} />
        </SmartLink>
    ) : null;

    let body;
    if (data.style === 'list') {
        body = (
            <ul className="grid gap-2 md:grid-cols-2">
                {items.map((item) => (
                    <li key={item.id}>
                        <PostCard item={item} variant="list" />
                    </li>
                ))}
            </ul>
        );
    } else if (data.style === 'featured') {
        const [lead, ...rest] = items;
        body = (
            <div className="grid gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3">
                    <PostCard item={lead} variant="featured" />
                </div>
                {rest.length > 0 && (
                    <ul className="space-y-2 lg:col-span-2">
                        {rest.map((item) => (
                            <li key={item.id}>
                                <PostCard item={item} variant="list" />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    } else {
        body = (
            <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item) => (
                    <li key={item.id}>
                        <PostCard item={item} />
                    </li>
                ))}
            </ul>
        );
    }

    return (
        <Section id={id} heading={str(data.heading)} action={more} className="vju-post-list-section">
            {body}
        </Section>
    );
}

// ---- steps -----------------------------------------------------------------
export function Steps({ data, id }: BlockProps<{ heading?: string; items?: { title?: string; text?: string }[] }>) {
    return (
        <Section id={id} heading={str(data.heading)} className="bg-surface">
            <ol className="grid gap-6 md:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]">
                {list<{ title?: string; text?: string }>(data.items).map((item, i) => (
                    <li key={i} className="relative rounded-lg border border-line bg-white p-6 pt-8 shadow-sm">
                        <span aria-hidden="true" className="absolute -top-5 left-6 flex h-10 w-10 items-center justify-center rounded-full bg-accent-600 text-lg font-bold text-white shadow">
                            {i + 1}
                        </span>
                        <h3 className="text-lg font-bold text-primary-900">
                            <span className="sr-only">{i + 1}. </span>
                            {item.title}
                        </h3>
                        {item.text && <p className="mt-2 text-sm text-muted">{item.text}</p>}
                    </li>
                ))}
            </ol>
        </Section>
    );
}

// ---- faq -------------------------------------------------------------------
export function Faq({ data, id }: BlockProps<{ heading?: string; items?: { question?: string; answer?: string }[] }>) {
    return (
        <Section id={id} heading={str(data.heading)}>
            <div className="max-w-3xl divide-y divide-line rounded-lg border border-line">
                {list<{ question?: string; answer?: string }>(data.items).map((item, i) => (
                    <details key={i} className="group">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-primary-900 hover:bg-surface [&::-webkit-details-marker]:hidden">
                            <h3>{item.question}</h3>
                            <ChevronDown className="shrink-0 transition group-open:rotate-180" />
                        </summary>
                        <div className="prose-content px-5 pb-5 text-base" dangerouslySetInnerHTML={{ __html: str(item.answer) }} />
                    </details>
                ))}
            </div>
        </Section>
    );
}

// ---- documents -------------------------------------------------------------
type DocItem = { title?: string; file?: FileRef | null; url?: string };

export function Documents({ data, id }: BlockProps<{ heading?: string; items?: DocItem[] }>) {
    const t = useT();
    const items = list<DocItem>(data.items).filter((d) => d.file?.url || d.url);
    if (!items.length) return null;
    return (
        <Section id={id} heading={str(data.heading)}>
            <ul className="max-w-3xl divide-y divide-line rounded-lg border border-line bg-white">
                {items.map((item, i) => {
                    const href = item.file?.url ?? (item.url as string);
                    const meta = [fileKind(item.file?.mime, href), item.file?.size].filter(Boolean).join(' · ');
                    return (
                        <li key={i} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <p className="font-semibold text-ink">{item.title}</p>
                                {meta && <p className="text-xs uppercase tracking-wide text-muted">{meta}</p>}
                            </div>
                            <a
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex shrink-0 items-center gap-2 self-start rounded-md bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 sm:self-center"
                            >
                                <DownloadIcon width={16} height={16} />
                                {t('download')}
                                <span className="sr-only">: {item.title}</span>
                            </a>
                        </li>
                    );
                })}
            </ul>
        </Section>
    );
}

// ---- cta -------------------------------------------------------------------
export function Cta({ data, id }: BlockProps<{ heading?: string; text?: string; image?: MediaImage | null; buttons?: ButtonData[] }>) {
    return (
        <section id={id} aria-labelledby={`${id}-h`} className="relative isolate scroll-mt-24 overflow-hidden bg-primary-800 py-14 text-white sm:py-20">
            {data.image && (
                <div aria-hidden="true" className="absolute inset-0 -z-20">
                    <Picture image={data.image} decorative className="h-full w-full object-cover" />
                </div>
            )}
            <div aria-hidden="true" className="absolute inset-0 -z-10 bg-gradient-to-r from-primary-950/95 to-primary-800/80" />
            <div className="container-site flex flex-col items-start gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div className="max-w-2xl">
                    <h2 id={`${id}-h`} className="text-2xl font-bold sm:text-3xl">
                        {str(data.heading)}
                    </h2>
                    {data.text && <p className="mt-3 text-white/90">{data.text}</p>}
                </div>
                <Buttons buttons={list<ButtonData>(data.buttons)} dark />
            </div>
        </section>
    );
}

// ---- logos -----------------------------------------------------------------
type LogoItem = { name?: string; image?: MediaImage | null; url?: string };

export function Logos({ data, id }: BlockProps<{ heading?: string; items?: LogoItem[] }>) {
    return (
        <Section id={id} heading={str(data.heading)}>
            <ul className="grid grid-cols-2 items-center gap-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7">
                {list<LogoItem>(data.items).map((item, i) => {
                    const img = item.image ? (
                        <img
                            src={item.image.thumb || item.image.url}
                            alt={item.name ?? item.image.alt ?? ''}
                            width={item.image.width ?? undefined}
                            height={item.image.height ?? undefined}
                            loading="lazy"
                            decoding="async"
                            className="mx-auto max-h-16 w-auto object-contain"
                        />
                    ) : (
                        <span className="font-semibold text-muted">{item.name}</span>
                    );
                    return (
                        <li key={i} className="flex h-24 items-center justify-center rounded-md border border-line bg-white p-4">
                            {item.url ? (
                                <SmartLink href={item.url} className="block">
                                    {img}
                                </SmartLink>
                            ) : (
                                img
                            )}
                        </li>
                    );
                })}
            </ul>
        </Section>
    );
}

// ---- video (lite YouTube embed) ------------------------------------------
export function Video({ data, id }: BlockProps<{ heading?: string; caption?: string; youtube_id?: string | null }>) {
    const t = useT();
    const { site } = useShared();
    const [playing, setPlaying] = useState(false);
    const ytId = data.youtube_id;
    if (!ytId) return null;
    const title = str(data.heading) || str(data.caption) || site.name;

    return (
        <Section id={id} heading={str(data.heading)}>
            <figure className="mx-auto max-w-4xl">
                <div className="relative aspect-video overflow-hidden rounded-lg bg-primary-950 shadow-lg">
                    {playing ? (
                        <iframe
                            src={`https://www.youtube-nocookie.com/embed/${encodeURIComponent(ytId)}?autoplay=1&rel=0`}
                            title={title}
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowFullScreen
                            className="absolute inset-0 h-full w-full border-0"
                        />
                    ) : (
                        <button type="button" onClick={() => setPlaying(true)} className="group absolute inset-0 h-full w-full">
                            <img
                                src={`https://i.ytimg.com/vi/${encodeURIComponent(ytId)}/hqdefault.jpg`}
                                alt=""
                                width={480}
                                height={360}
                                loading="lazy"
                                decoding="async"
                                className="h-full w-full object-cover opacity-90 transition group-hover:opacity-100"
                            />
                            <span className="absolute left-1/2 top-1/2 flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-accent-600 text-white shadow-lg transition group-hover:scale-110 motion-reduce:group-hover:scale-100">
                                <PlayIcon width={28} height={28} />
                            </span>
                            <span className="sr-only">
                                {t('play_video')}: {title}
                            </span>
                        </button>
                    )}
                </div>
                {data.caption && <figcaption className="mt-3 text-center text-sm text-muted">{data.caption}</figcaption>}
            </figure>
        </Section>
    );
}
