import type { JSX, SVGProps } from 'react';

// Small inline icons (no icon library). Decorative by default.
const base = (props: SVGProps<SVGSVGElement>) => ({
    width: 20,
    height: 20,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
    focusable: false,
    ...props,
});

export const ChevronDown = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="m6 9 6 6 6-6" />
    </svg>
);
export const ChevronRight = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="m9 6 6 6-6 6" />
    </svg>
);
export const ChevronLeft = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="m15 6-6 6 6 6" />
    </svg>
);
export const MenuIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
);
export const CloseIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M6 6l12 12M18 6 6 18" />
    </svg>
);
export const SearchIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-3.5-3.5" />
    </svg>
);
export const PhoneIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />
    </svg>
);
export const MailIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <path d="m3 7 9 6 9-6" />
    </svg>
);
export const PinIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z" />
        <circle cx="12" cy="9" r="2.5" />
    </svg>
);
export const DownloadIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14" />
    </svg>
);
export const CalendarIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M16 3v4M8 3v4M3 10h18" />
    </svg>
);
export const LinkIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" />
    </svg>
);
export const ExternalIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base({ width: 14, height: 14, ...p })}>
        <path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
    </svg>
);
export const PauseIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base(p)}>
        <path d="M9 5v14M15 5v14" />
    </svg>
);
export const PlayIcon = (p: SVGProps<SVGSVGElement>) => (
    <svg {...base({ fill: 'currentColor', stroke: 'none', ...p })}>
        <path d="M8 5.5v13a1 1 0 0 0 1.5.9l10-6.5a1 1 0 0 0 0-1.7l-10-6.5A1 1 0 0 0 8 5.5Z" />
    </svg>
);

// Brand glyphs (filled, 24x24).
const brand = (d: string) => (p: SVGProps<SVGSVGElement>) => (
    <svg {...base({ fill: 'currentColor', stroke: 'none', ...p })}>
        <path d={d} />
    </svg>
);

export const SocialIcons: Record<string, (p: SVGProps<SVGSVGElement>) => JSX.Element> = {
    facebook: brand('M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4A21 21 0 0 0 14.3 4C12 4 10.5 5.4 10.5 8v2.5H8v3h2.5V21h3Z'),
    youtube: brand('M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z'),
    linkedin: brand('M6.9 8.5H3.6V20h3.3V8.5ZM5.3 3a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8ZM20.4 13.4c0-3.1-1.7-5.1-4.4-5.1-1.5 0-2.5.8-3 1.5V8.5H9.8V20h3.3v-6c0-1.5.6-2.6 2-2.6s1.9 1.1 1.9 2.6v6h3.4v-6.6Z'),
    instagram: brand('M12 7.3a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4Zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm4.9-8.9a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2ZM12 3.6c2.7 0 3 0 4.1.1 2.7.1 4 1.4 4.1 4.1.1 1.1.1 1.4.1 4.1s0 3-.1 4.1c-.1 2.7-1.4 4-4.1 4.1-1.1.1-1.4.1-4.1.1s-3 0-4.1-.1c-2.7-.1-4-1.4-4.1-4.1C3.7 15 3.6 14.7 3.6 12s0-3 .1-4.1c.1-2.7 1.4-4 4.1-4.1 1.1-.1 1.4-.1 4.2-.1Z'),
    tiktok: brand('M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-1.8-2.5V9.8a5.7 5.7 0 1 0 4.9 5.6V9.1a7.3 7.3 0 0 0 4.3 1.4V7.4a4.3 4.3 0 0 1-3.2-1.6Z'),
    x: brand('M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z'),
};
