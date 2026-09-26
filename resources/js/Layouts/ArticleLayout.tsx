import type { ReactNode } from 'react';
import Breadcrumb from '@/Components/Breadcrumb/Breadcrumb';
import type { Crumb } from '@/Types';
import PublicLayout from './PublicLayout';

/** Reading layout: breadcrumbs + a comfortable text column; `after` spans the full width (related posts...). */
export default function ArticleLayout({ breadcrumbs, children, after }: { breadcrumbs: Crumb[]; children: ReactNode; after?: ReactNode }) {
    return (
        <PublicLayout>
            <div className="container-site pt-6">
                <Breadcrumb items={breadcrumbs} />
            </div>
            <div className="container-site py-8">
                <div className="mx-auto max-w-3xl">{children}</div>
            </div>
            {after}
        </PublicLayout>
    );
}
