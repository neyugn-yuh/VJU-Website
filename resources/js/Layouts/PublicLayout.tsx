import { Head } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import Footer, { PublicFloatingActions } from '@/Components/Footer/Footer';
import Header from '@/Components/Header/Header';
import { CloseIcon } from '@/Components/Navigation/Icons';
import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';

/** Flash messages from the session (e.g. comment submitted). Fixed toast so it is visible wherever the user is. */
function Flash() {
    const { flash } = useShared();
    const t = useT();
    const [dismissed, setDismissed] = useState<string | null>(null);
    const message = flash?.error ?? flash?.success ?? null;
    const isError = !!flash?.error;

    useEffect(() => setDismissed(null), [message]);

    // Live region stays mounted so screen readers announce the text when it appears.
    return (
        <div
            role={isError ? 'alert' : 'status'}
            aria-live={isError ? 'assertive' : 'polite'}
            className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4"
        >
            {message && dismissed !== message && (
                <div
                    className={`pointer-events-auto flex max-w-lg items-start gap-3 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg ${
                        isError ? 'bg-accent-700' : 'bg-primary-800'
                    }`}
                >
                    <p className="flex-1">{message}</p>
                    <button type="button" onClick={() => setDismissed(message)} className="-m-1 rounded p-1 hover:bg-white/15">
                        <CloseIcon width={16} height={16} />
                        <span className="sr-only">{t('close')}</span>
                    </button>
                </div>
            )}
        </div>
    );
}

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { seo, preview } = useShared();
    const t = useT();

    return (
        <>
            {/* Full SEO tags come from app.blade.php; only keep title/description in sync on client visits. */}
            <Head title={seo?.title}>{seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}</Head>
            <a
                href="#main-content"
                className="sr-only z-[100] rounded-md bg-primary-700 px-4 py-2 font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >
                {t('skip_to_content')}
            </a>
            <div className="flex min-h-screen flex-col bg-white">
                <Header />
                <main id="main-content" tabIndex={-1} className="flex-1 focus:outline-none focus-visible:shadow-none">
                    {preview === true && (
                        <p role="note" className="bg-amber-300 px-4 py-2 text-center text-sm font-semibold text-amber-950">
                            {t('preview_banner')}
                        </p>
                    )}
                    {children}
                </main>
                <Footer />
                <PublicFloatingActions />
            </div>
            <Flash />
        </>
    );
}
