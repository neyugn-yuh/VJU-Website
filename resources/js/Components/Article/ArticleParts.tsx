import { Link, useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import type { CommentItem, ContentFull, StructuredFields } from '@/Types';
import { fileKind, formatDate } from '@/Utils/format';
import { CalendarIcon, DownloadIcon, ExternalIcon, LinkIcon, PinIcon, SocialIcons } from '../Navigation/Icons';

/** Structured-type fields (document / notification / tuition_fee / opportunity). */
export function FieldsPanel({ fields }: { fields: StructuredFields }) {
    const t = useT();
    const { locale } = useShared();
    const rows: [string, string][] = [];
    if (fields.number) rows.push([t('number'), fields.number]);
    if (fields.issued_at) rows.push([t('issued_at'), formatDate(fields.issued_at, locale)]);
    if (fields.program) rows.push([t('program'), fields.program]);
    if (fields.academic_year) rows.push([t('academic_year'), fields.academic_year]);
    if (fields.work_type) rows.push([t('work_type'), fields.work_type]);
    if (fields.location) rows.push([t('location'), fields.location]);
    if (fields.deadline) rows.push([t('deadline'), formatDate(fields.deadline, locale)]);
    const { file, apply_url } = fields;
    if (!rows.length && !file && !apply_url) return null;

    return (
        <aside className="my-8 rounded-lg border border-primary-100 bg-primary-50 p-5 sm:p-6">
            {rows.length > 0 && (
                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    {rows.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-primary-700">{label}</dt>
                            <dd className="mt-0.5 font-medium text-ink">{value}</dd>
                        </div>
                    ))}
                </dl>
            )}
            {(file || apply_url) && (
                <div className={`flex flex-wrap gap-3 ${rows.length ? 'mt-5 border-t border-primary-100 pt-5' : ''}`}>
                    {file && (
                        <a
                            href={file.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-md bg-primary-700 px-5 py-3 font-semibold text-white hover:bg-primary-800"
                        >
                            <DownloadIcon />
                            {t('download')}
                            {(file.size || file.mime || file.name) && (
                                <span className="text-sm font-normal text-white/85">
                                    ({[fileKind(file.mime, file.name || file.url), file.size].filter(Boolean).join(', ')})
                                </span>
                            )}
                        </a>
                    )}
                    {apply_url && (
                        <a
                            href={apply_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-md bg-accent-600 px-5 py-3 font-semibold text-white hover:bg-accent-700"
                        >
                            {t('apply')}
                            <ExternalIcon />
                        </a>
                    )}
                </div>
            )}
        </aside>
    );
}

export function ArticleMeta({ content }: { content: ContentFull }) {
    const t = useT();
    const { locale } = useShared();
    return (
        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
            {content.date && (
                <span className="inline-flex items-center gap-1.5">
                    <CalendarIcon width={16} height={16} />
                    <span className="sr-only">{t('published_on')}</span>
                    <time dateTime={content.date}>{formatDate(content.date, locale)}</time>
                </span>
            )}
            {content.author && (
                <span>
                    {t('by')} <span className="font-medium text-ink">{content.author}</span>
                </span>
            )}
            {content.fields?.location && (
                <span className="inline-flex items-center gap-1.5">
                    <PinIcon width={16} height={16} />
                    {content.fields.location}
                </span>
            )}
        </div>
    );
}

export function ShareLinks({ url, title }: { url: string; title: string }) {
    const t = useT();
    const [copied, setCopied] = useState(false);
    const u = encodeURIComponent(url);
    const links = [
        { key: 'facebook', name: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
        { key: 'linkedin', name: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}` },
        { key: 'x', name: 'X', href: `https://twitter.com/intent/tweet?url=${u}&text=${encodeURIComponent(title)}` },
    ];
    const btn = 'flex h-10 w-10 items-center justify-center rounded-full border border-line text-primary-800 hover:border-primary-700 hover:bg-primary-700 hover:text-white';

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-3">
            <h2 className="text-sm font-semibold text-ink">{t('share')}</h2>
            <ul className="flex items-center gap-2">
                {links.map((l) => {
                    const Icon = SocialIcons[l.key];
                    return (
                        <li key={l.key}>
                            <a href={l.href} target="_blank" rel="noopener noreferrer" className={btn}>
                                <Icon width={18} height={18} />
                                <span className="sr-only">{l.name}</span>
                            </a>
                        </li>
                    );
                })}
                <li>
                    <button type="button" onClick={copy} className={btn}>
                        <LinkIcon width={18} height={18} />
                        <span className="sr-only">{t('copy_link')}</span>
                    </button>
                </li>
            </ul>
            <span role="status" className="text-sm font-medium text-primary-700">
                {copied ? t('link_copied') : ''}
            </span>
        </div>
    );
}

function Field({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-ink">
                {label} <span aria-hidden="true" className="text-accent-600">*</span>
            </label>
            {children}
            {error && (
                <p id={`${id}-error`} className="mt-1 text-sm font-medium text-accent-700">
                    {error}
                </p>
            )}
        </div>
    );
}

export function Comments({ contentId, comments }: { contentId: number; comments: CommentItem[] }) {
    const t = useT();
    const { locale } = useShared();
    const id = useId();
    const form = useForm({ name: '', email: '', body: '', website: '' });
    const { data, setData, errors, processing } = form;
    const input = 'w-full rounded-md border border-line bg-white px-3 py-2 text-ink aria-[invalid=true]:border-accent-600';
    const a11y = (field: keyof typeof data) => ({
        'aria-invalid': !!errors[field],
        'aria-describedby': errors[field] ? `${id}-${field}-error` : undefined,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/comments/${contentId}`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <section aria-labelledby={`${id}-h`} className="mt-12 border-t border-line pt-8">
            <h2 id={`${id}-h`} className="text-2xl font-bold text-primary-900">
                {t('comments')} {comments.length > 0 && <span className="text-muted">({comments.length})</span>}
            </h2>
            {comments.length > 0 && (
                <ol className="mt-6 space-y-5">
                    {comments.map((c) => (
                        <li key={c.id} className="rounded-lg bg-surface p-4">
                            <p className="flex flex-wrap items-baseline gap-x-3 text-sm">
                                <span className="font-semibold text-ink">{c.name}</span>
                                <time dateTime={c.date} className="text-muted">
                                    {formatDate(c.date, locale)}
                                </time>
                            </p>
                            <p className="mt-2 whitespace-pre-line text-ink">{c.body}</p>
                        </li>
                    ))}
                </ol>
            )}

            <form onSubmit={submit} noValidate className="mt-8 space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id={`${id}-name`} label={t('comment_name')} error={errors.name}>
                        <input
                            id={`${id}-name`}
                            name="name"
                            autoComplete="name"
                            required
                            maxLength={100}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={input}
                            {...a11y('name')}
                        />
                    </Field>
                    <Field id={`${id}-email`} label={t('comment_email')} error={errors.email}>
                        <input
                            id={`${id}-email`}
                            name="email"
                            type="email"
                            autoComplete="email"
                            required
                            maxLength={190}
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className={input}
                            {...a11y('email')}
                        />
                    </Field>
                </div>
                <Field id={`${id}-body`} label={t('comment_body')} error={errors.body}>
                    <textarea
                        id={`${id}-body`}
                        name="body"
                        rows={5}
                        required
                        minLength={3}
                        maxLength={5000}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        className={input}
                        {...a11y('body')}
                    />
                </Field>
                {/* Honeypot: hidden from people and assistive tech; bots fill it and get rejected. */}
                <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                    <label htmlFor={`${id}-website`}>{t('website')}</label>
                    <input
                        id={`${id}-website`}
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        value={data.website}
                        onChange={(e) => setData('website', e.target.value)}
                    />
                </div>
                {errors.website && <p className="text-sm font-medium text-accent-700">{errors.website}</p>}
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center rounded-md bg-primary-700 px-5 py-2.5 font-semibold text-white hover:bg-primary-800 disabled:opacity-60"
                >
                    {t('comment_submit')}
                </button>
            </form>
        </section>
    );
}

export function TagList({ tags }: { tags: ContentFull['tags'] }) {
    const t = useT();
    if (!tags.length) return null;
    return (
        <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-sm font-semibold text-ink">{t('tags')}:</h2>
            <ul className="flex flex-wrap gap-2">
                {tags.map((tag) => (
                    <li key={tag.url}>
                        <Link prefetch="hover" viewTransition href={tag.url} className="inline-block rounded-full border border-line px-3 py-1 text-sm text-primary-800 hover:border-primary-700 hover:bg-primary-50">
                            #{tag.name}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
