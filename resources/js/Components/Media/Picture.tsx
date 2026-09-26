import type { MediaImage } from '@/Types';

interface Props {
    image: MediaImage;
    className?: string;
    /** LCP image: eager + high priority. Everything else is lazy. */
    priority?: boolean;
    /** Use the small derivative (cards). */
    thumb?: boolean;
    /** Empty alt when the image is decorative (the surrounding text already says it). */
    decorative?: boolean;
}

/** <picture> with AVIF/WebP sources; intrinsic width/height reserve space (no CLS). */
export default function Picture({ image, className, priority = false, thumb = false, decorative = false }: Props) {
    const src = thumb ? image.thumb || image.url : image.url;
    // AVIF/WebP derivatives are full size: only offer them when we are not showing the thumb.
    const modern = !thumb || src === image.url;

    return (
        <picture>
            {modern && image.avif && <source srcSet={image.avif} type="image/avif" />}
            {modern && image.webp && <source srcSet={image.webp} type="image/webp" />}
            <img
                src={src}
                alt={decorative ? '' : (image.alt ?? '')}
                width={image.width ?? undefined}
                height={image.height ?? undefined}
                loading={priority ? 'eager' : 'lazy'}
                fetchPriority={priority ? 'high' : 'auto'}
                decoding="async"
                className={className}
            />
        </picture>
    );
}
