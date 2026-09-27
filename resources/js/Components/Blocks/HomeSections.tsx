import { useState } from 'react';
import type { MediaImage } from '@/Types';
import { PlayIcon } from '../Navigation/Icons';
import SmartLink from '../Navigation/SmartLink';
import Picture from '../Media/Picture';
import { Buttons, list, Section, str, type BlockProps, type ButtonData } from './shared';

type IntroData = {
    heading?: string;
    body?: string;
    image?: MediaImage | null;
    youtube_id?: string | null;
    buttons?: ButtonData[];
};

/** The three-column introduction section used directly below the homepage banner. */
export function Intro({ data, id }: BlockProps<IntroData>) {
    return (
        <Section id={id} heading={str(data.heading)} className="vju-home-intro">
            <div className="vju-home-intro-grid">
                {data.image && (
                    <figure className="vju-home-intro-image">
                        <Picture image={data.image} decorative className="h-full w-full object-cover" />
                        {data.image.caption && <figcaption>{data.image.caption}</figcaption>}
                    </figure>
                )}
                <div className="vju-home-intro-copy">
                    <div className="prose-content" dangerouslySetInnerHTML={{ __html: str(data.body) }} />
                    <Buttons buttons={list<ButtonData>(data.buttons)} />
                </div>
                {data.youtube_id && <VideoPreview youtubeId={data.youtube_id} title={str(data.heading)} />}
            </div>
        </Section>
    );
}

function VideoPreview({ youtubeId, title }: { youtubeId: string; title: string }) {
    const [playing, setPlaying] = useState(false);

    return (
        <div className="vju-home-video relative aspect-video overflow-hidden rounded-md bg-primary-950">
            {playing ? (
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0`}
                    title={title || 'VJU video'}
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="absolute inset-0 h-full w-full border-0"
                />
            ) : (
                <button type="button" onClick={() => setPlaying(true)} className="group absolute inset-0 h-full w-full">
                    <img
                        src={`https://i.ytimg.com/vi/${encodeURIComponent(youtubeId)}/hqdefault.jpg`}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        className="h-full w-full object-cover opacity-80 transition group-hover:opacity-100"
                    />
                    <span className="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-accent-600 text-white shadow-lg transition group-hover:scale-110 motion-reduce:group-hover:scale-100">
                        <PlayIcon width={26} height={26} />
                    </span>
                    <span className="sr-only">Phát video</span>
                </button>
            )}
        </div>
    );
}

type ProgramItem = { title?: string; text?: string; image?: MediaImage | null; url?: string };

/** Hover cards mirror the flip-box program selector from the crawled homepage. */
export function Programs({ data, id }: BlockProps<{ heading?: string; items?: ProgramItem[] }>) {
    return (
        <Section id={id} heading={str(data.heading)} className="vju-home-programs">
            <ul className="vju-home-program-grid">
                {list<ProgramItem>(data.items).map((item, i) => (
                    <li key={i} className="vju-home-program-card">
                        {item.image && <Picture image={item.image} decorative className="vju-home-program-image" />}
                        <div className="vju-home-program-overlay">
                            <h3>{item.title}</h3>
                            {item.text && <p>{item.text}</p>}
                            {item.url && (
                                <SmartLink href={item.url} className="vju-home-program-link">
                                    Tìm hiểu thêm
                                </SmartLink>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

type GalleryItem = { image?: MediaImage | null; title?: string; url?: string };

export function ActivityGallery({ data, id }: BlockProps<{ heading?: string; items?: GalleryItem[] }>) {
    return (
        <Section id={id} heading={str(data.heading)} className="vju-home-activities">
            <ul className="vju-home-activity-grid">
                {list<GalleryItem>(data.items).map((item, i) =>
                    item.image ? (
                        <li key={i} className={`vju-home-activity-item vju-home-activity-item-${i + 1}`}>
                            {item.url ? (
                                <SmartLink href={item.url} className="block h-full">
                                    <Picture image={item.image} decorative className="h-full w-full object-cover transition duration-300 hover:scale-105" />
                                </SmartLink>
                            ) : (
                                <Picture image={item.image} decorative className="h-full w-full object-cover" />
                            )}
                            {item.title && <span>{item.title}</span>}
                        </li>
                    ) : null,
                )}
            </ul>
        </Section>
    );
}

type ContactData = {
    heading?: string;
    map_url?: string;
    address?: string;
    address_hola?: string;
    phone?: string;
    email?: string;
};

export function Contact({ data, id }: BlockProps<ContactData>) {
    return (
        <Section id={id} heading={str(data.heading)} className="vju-home-contact">
            <div className="vju-home-contact-grid">
                <div className="vju-home-map">
                    {data.map_url ? (
                        <iframe src={data.map_url} title="Bản đồ Trường Đại học Việt Nhật" loading="lazy" />
                    ) : (
                        <div className="vju-map-placeholder">Vietnam Japan University</div>
                    )}
                </div>
                <address className="vju-home-contact-details">
                    {data.address && <p><strong>Cơ sở Mỹ Đình:</strong><br />{data.address}</p>}
                    {data.address_hola && <p><strong>Cơ sở Hòa Lạc:</strong><br />{data.address_hola}</p>}
                    {data.phone && <p><strong>Hotline:</strong><br />{data.phone}</p>}
                    {data.email && <p><strong>Email:</strong><br /><a href={`mailto:${data.email}`}>{data.email}</a></p>}
                </address>
            </div>
        </Section>
    );
}
