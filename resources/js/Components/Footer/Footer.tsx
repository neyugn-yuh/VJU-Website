import { useShared } from '@/Hooks/useShared';
import { useT } from '@/Hooks/useT';
import { MailIcon, PhoneIcon, PinIcon, SocialIcons } from '../Navigation/Icons';
import SmartLink from '../Navigation/SmartLink';

const SOCIAL_NAMES: Record<string, string> = {
    facebook: 'Facebook',
    youtube: 'YouTube',
    linkedin: 'LinkedIn',
    instagram: 'Instagram',
    tiktok: 'TikTok',
};

export default function Footer() {
    const { site, menus } = useShared();
    const t = useT();
    const items = menus?.footer ?? [];
    const { phone, email, address, map_url } = site.contact ?? {};
    // PHP serialises an empty settings array as [], so accept both shapes.
    const social = Object.entries(site.social ?? {}).filter(([, url]) => typeof url === 'string' && url);
    const groups = items.filter((i) => i.children.length);
    const singles = items.filter((i) => !i.children.length);

    return (
        <footer className="mt-16 bg-primary-950 text-white/85">
            <div className="container-site grid gap-10 py-12 md:grid-cols-2 lg:grid-cols-4">
                <div className="lg:col-span-2">
                    <p className="text-lg font-bold text-white">{site.name}</p>
                    {site.description && <p className="mt-2 max-w-prose text-sm">{site.description}</p>}
                    {(address || phone || email) && (
                        <address className="mt-6 space-y-2 text-sm not-italic">
                            <h2 className="sr-only">{t('contact')}</h2>
                            {address && (
                                <p className="flex gap-2">
                                    <PinIcon width={18} height={18} className="mt-0.5 shrink-0 text-accent-500" />
                                    {map_url ? (
                                        <a href={map_url} target="_blank" rel="noopener noreferrer" className="hover:text-white hover:underline">
                                            {address}
                                        </a>
                                    ) : (
                                        <span>{address}</span>
                                    )}
                                </p>
                            )}
                            {phone && (
                                <p className="flex gap-2">
                                    <PhoneIcon width={18} height={18} className="mt-0.5 shrink-0 text-accent-500" />
                                    <a href={`tel:${phone.replace(/[^\d+]/g, '')}`} className="hover:text-white hover:underline">
                                        {phone}
                                    </a>
                                </p>
                            )}
                            {email && (
                                <p className="flex gap-2">
                                    <MailIcon width={18} height={18} className="mt-0.5 shrink-0 text-accent-500" />
                                    <a href={`mailto:${email}`} className="hover:text-white hover:underline">
                                        {email}
                                    </a>
                                </p>
                            )}
                        </address>
                    )}
                </div>

                {groups.map((group, i) => (
                    <nav key={i} aria-label={group.label}>
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-white">{group.label}</h2>
                        <ul className="space-y-2 text-sm">
                            {group.children.map((child, j) => (
                                <li key={j}>
                                    <SmartLink href={child.url} target={child.target} className="hover:text-white hover:underline">
                                        {child.label}
                                    </SmartLink>
                                </li>
                            ))}
                        </ul>
                    </nav>
                ))}

                {(singles.length > 0 || social.length > 0) && (
                    <div className="space-y-6">
                        {singles.length > 0 && (
                            <nav aria-label={t('menu')}>
                                <ul className="space-y-2 text-sm">
                                    {singles.map((item, i) => (
                                        <li key={i}>
                                            <SmartLink href={item.url} target={item.target} className="hover:text-white hover:underline">
                                                {item.label}
                                            </SmartLink>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        )}
                        {social.length > 0 && (
                            <div>
                                <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-white">{t('follow_us')}</h2>
                                <ul className="flex flex-wrap gap-2">
                                    {social.map(([key, url]) => {
                                        const Icon = SocialIcons[key];
                                        return (
                                            <li key={key}>
                                                <a
                                                    href={url as string}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-accent-600"
                                                >
                                                    {Icon ? <Icon /> : key.slice(0, 2)}
                                                    <span className="sr-only">{SOCIAL_NAMES[key] ?? key}</span>
                                                </a>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div className="border-t border-white/10">
                <p className="container-site py-4 text-xs text-white/70">
                    © {new Date().getFullYear()} {site.name}
                </p>
            </div>
        </footer>
    );
}
