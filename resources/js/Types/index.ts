// Mirrors the arrays built in app/Http/Presenters/ContentPresenter.php,
// app/Http/Controllers/PublicController.php and app/Http/Middleware/HandleInertiaRequests.php.

export type Locale = 'vi' | 'en' | 'ja';

export interface MediaImage {
    url: string;
    original: string;
    alt: string | null;
    width: number | null;
    height: number | null;
    thumb: string;
    webp: string | null;
    avif: string | null;
    caption: string | null;
}

export interface FileRef {
    url: string;
    mime: string | null;
    size: string | null;
    name?: string;
}

export interface LinkRef {
    name: string;
    url: string;
}

export interface Crumb {
    label: string;
    url: string | null;
}

export interface MenuNode {
    label: string;
    url: string;
    target: string | null;
    icon: string | null;
    children: MenuNode[];
}

export interface StructuredFields {
    file?: FileRef;
    number?: string;
    issued_at?: string;
    program?: string;
    academic_year?: string;
    location?: string;
    work_type?: string;
    apply_url?: string;
    deadline?: string;
}

export type ContentTypeKey = 'post' | 'page' | 'document' | 'notification' | 'tuition_fee' | 'opportunity';

export interface ContentCard {
    id: number;
    type: ContentTypeKey;
    title: string;
    url: string;
    excerpt: string;
    date: string | null;
    image: MediaImage | null;
    category: LinkRef | null;
    fields: StructuredFields | null;
}

export interface Block<T = Record<string, unknown>> {
    type: string;
    data: T;
}

export interface ContentFull extends ContentCard {
    body: string; // sanitized server-side, safe for dangerouslySetInnerHTML
    blocks: Block[];
    template: 'default' | 'landing' | 'admissions' | 'education' | 'research' | 'contact';
    updated: string | null;
    author: string | null;
    categories: LinkRef[];
    tags: LinkRef[];
    commentable: boolean;
}

export interface Pagination {
    current: number;
    last: number;
    total: number;
    pages: { n: number; url: string }[];
    prev: string | null;
    next: string | null;
}

export interface Alternate {
    locale: Locale;
    url: string;
}

export interface CommentItem {
    id: number;
    name: string;
    body: string; // plain text
    date: string;
}

export interface Seo {
    title: string;
    description?: string | null;
    robots?: string;
    canonical?: string;
}

export interface SiteInfo {
    name: string;
    description: string | null;
    logo: string | null;
    contact: { phone: string | null; email: string | null; address: string | null; map_url: string | null };
    social: Partial<Record<'facebook' | 'youtube' | 'linkedin' | 'instagram' | 'tiktok', string>>;
    analytics: { ga: string | null; gtm: string | null };
}

export type Strings = Record<string, string> & { types: Record<ContentTypeKey, string> };

export interface SharedProps {
    locale: Locale;
    locales: { code: Locale; name: string; short: string; home: string }[];
    t: Strings;
    site: SiteInfo;
    menus: { header: MenuNode[]; topbar: MenuNode[]; footer: MenuNode[] };
    flash: { success: string | null; error: string | null };
    alternates: Alternate[];
    seo: Seo;
    [key: string]: unknown;
}

export interface HomeProps extends SharedProps {
    blocks: Block[];
    latest: ContentCard[]; // used only when no homepage is configured
}

export interface ArticleProps extends SharedProps {
    content: ContentFull;
    breadcrumbs: Crumb[];
    related: ContentCard[];
    comments: CommentItem[];
    preview: boolean;
}

export type PageProps = ArticleProps;

export interface ListingProps extends SharedProps {
    title: string;
    description: string | null;
    type: ContentTypeKey | null;
    items: ContentCard[];
    pagination: Pagination;
    subcategories: LinkRef[];
    breadcrumbs: Crumb[];
}

export interface SearchProps extends SharedProps {
    q: string;
    items: ContentCard[];
    pagination: Pagination | null;
}

export interface ErrorProps extends SharedProps {
    status: number;
    latest: ContentCard[];
}
