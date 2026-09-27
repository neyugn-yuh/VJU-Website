import { useEffect, useState } from 'react';
import { useT } from '@/Hooks/useT';
import type { MediaImage } from '@/Types';
import Picture from '../Media/Picture';
import { PauseIcon, PlayIcon } from '../Navigation/Icons';
import { Buttons, list, str, type BlockProps, type ButtonData } from './shared';

type Slide = { image?: MediaImage | null; caption?: string };
type Data = { heading?: string; subheading?: string; slides?: Slide[]; buttons?: ButtonData[] };

const INTERVAL = 7000;

export default function Hero({ data, id, first, asTitle }: BlockProps<Data>) {
    const t = useT();
    const slides = list<Slide>(data.slides).filter((s) => s.image);
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    // Autoplay starts only on the client, and never when the user prefers reduced motion.
    const [reducedMotion, setReducedMotion] = useState(true);
    const H = asTitle ? 'h1' : 'h2';
    const multiple = slides.length > 1;

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => setReducedMotion(mq.matches);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (!multiple || paused || reducedMotion) return;
        const timer = window.setTimeout(() => setIndex((i) => (i + 1) % slides.length), INTERVAL);
        return () => window.clearTimeout(timer);
    }, [index, multiple, paused, reducedMotion, slides.length]);

    const caption = slides[index]?.caption;
    const imageOnly = Boolean(slides[0]?.image && Number(slides[0].image.width) / Math.max(1, Number(slides[0].image.height)) > 2.3);

    return (
        <section
            id={id}
            aria-roledescription={multiple ? 'carousel' : undefined}
            aria-labelledby={data.heading && !imageOnly ? `${id}-h` : undefined}
            className={`vju-hero relative isolate overflow-hidden ${imageOnly ? 'vju-hero-image-only' : 'bg-primary-900 text-white'}`}
        >
            {slides.map((slide, i) => (
                <div
                    key={i}
                    aria-hidden={i !== index}
                    className={`absolute inset-0 transition-opacity duration-1000 ${i === index ? 'opacity-100' : 'pointer-events-none opacity-0'}`}
                >
                    <Picture
                        image={slide.image as MediaImage}
                        priority={first && i === 0}
                        decorative
                        className="h-full w-full object-cover"
                    />
                </div>
            ))}
            {!imageOnly && <div aria-hidden="true" className="absolute inset-0 -z-10 bg-gradient-to-r from-primary-950/90 via-primary-900/70 to-primary-900/20" />}
            {!imageOnly && <div aria-hidden="true" className="absolute inset-x-0 bottom-0 -z-10 h-1/2 bg-gradient-to-t from-primary-950/70 to-transparent" />}

            {!imageOnly && (
                <div className="container-site flex min-h-[26rem] flex-col justify-center py-16 sm:min-h-[32rem] lg:min-h-[36rem]">
                    <div className="max-w-3xl">
                        <H id={`${id}-h`} className="text-3xl font-extrabold leading-tight drop-shadow sm:text-5xl lg:text-6xl">
                            {str(data.heading)}
                        </H>
                        {data.subheading && <p className="mt-4 max-w-2xl text-lg text-white/90 sm:text-xl">{data.subheading}</p>}
                        <div className="mt-8"><Buttons buttons={list<ButtonData>(data.buttons)} dark /></div>
                    </div>
                </div>
            )}

            {(multiple || caption) && (
                <div className={`container-site absolute inset-x-0 bottom-4 flex items-center justify-between gap-4 ${imageOnly ? 'vju-hero-controls' : ''}`}>
                    {multiple ? (
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setPaused(!paused)}
                                aria-pressed={paused}
                                className="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 backdrop-blur hover:bg-white/30"
                            >
                                {paused ? <PlayIcon width={16} height={16} /> : <PauseIcon width={16} height={16} />}
                                <span className="sr-only">{t(paused ? 'play' : 'pause')}</span>
                            </button>
                            <ul className="flex items-center gap-1">
                                {slides.map((_, i) => (
                                    <li key={i}>
                                        <button
                                            type="button"
                                            onClick={() => setIndex(i)}
                                            aria-current={i === index ? 'true' : undefined}
                                            className="flex h-9 w-7 items-center justify-center"
                                        >
                                            <span className={`block h-1.5 rounded-full transition-all ${i === index ? 'w-6 bg-white' : 'w-3 bg-white/50'}`} />
                                            <span className="sr-only">
                                                {i + 1} / {slides.length}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <span />
                    )}
                    {caption && <p className="text-right text-xs text-white/80">{caption}</p>}
                </div>
            )}
        </section>
    );
}
