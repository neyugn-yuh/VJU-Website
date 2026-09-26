import type { ReactNode } from 'react';
import Breadcrumb from '@/Components/Breadcrumb/Breadcrumb';
import type { Crumb } from '@/Types';
import PublicLayout from './PublicLayout';

interface Props {
    title: string;
    description?: string | null;
    breadcrumbs?: Crumb[];
    /** Extra content in the header band (search form, chips...). */
    header?: ReactNode;
    children: ReactNode;
}

export default function ListingLayout({ title, description, breadcrumbs, header, children }: Props) {
    return (
        <PublicLayout>
            <div className="border-b border-line bg-surface">
                <div className="container-site py-8 sm:py-10">
                    {breadcrumbs && <Breadcrumb items={breadcrumbs} />}
                    <h1 className="mt-3 text-3xl font-bold text-primary-900 sm:text-4xl">{title}</h1>
                    {description && <p className="mt-3 max-w-3xl text-muted">{description}</p>}
                    {header && <div className="mt-6">{header}</div>}
                </div>
            </div>
            <div className="container-site py-10">{children}</div>
        </PublicLayout>
    );
}
