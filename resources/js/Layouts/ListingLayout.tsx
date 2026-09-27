import type { ReactNode } from 'react';
import Breadcrumb from '@/Components/Breadcrumb/Breadcrumb';
import type { Crumb } from '@/Types';
import type { MediaImage } from '@/Types';
import PublicLayout from './PublicLayout';

interface Props {
    title: string;
    description?: string | null;
    breadcrumbs?: Crumb[];
    /** Extra content in the header band (search form, chips...). */
    header?: ReactNode;
    heroImage?: MediaImage | null;
    children: ReactNode;
}

export default function ListingLayout({ title, description, breadcrumbs, header, heroImage, children }: Props) {
    return (
        <PublicLayout>
            <div className={`vju-page-hero ${heroImage ? 'vju-page-hero-image' : ''}`}>
                {heroImage && <img src={heroImage.url} alt="" aria-hidden="true" />}
                <div className="vju-page-hero-overlay" />
                <div className="container-site relative py-8 sm:py-10">
                    {breadcrumbs && <Breadcrumb items={breadcrumbs} invert={Boolean(heroImage)} />}
                    <h1 className="mt-3 text-3xl font-bold sm:text-4xl">{title}</h1>
                    {description && <p className="mt-3 max-w-3xl">{description}</p>}
                </div>
            </div>
            {header && <div className="container-site vju-listing-header">{header}</div>}
            <div className="container-site py-10">{children}</div>
        </PublicLayout>
    );
}
