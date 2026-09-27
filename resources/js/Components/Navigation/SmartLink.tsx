import { Link } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { isInternal } from '@/Utils/url';

interface Props {
    href: string;
    target?: string | null;
    className?: string;
    style?: CSSProperties;
    children: ReactNode;
}

/** Internal "/..." -> Inertia Link; http(s) or target=_blank -> new tab; #, mailto:, tel: -> plain <a>. */
export default function SmartLink({ href, target, children, ...rest }: Props) {
    if (target === '_blank' || /^https?:\/\//i.test(href) || href.startsWith('//')) {
        return (
            <a href={href} target="_blank" rel="noopener noreferrer" {...rest}>
                {children}
            </a>
        );
    }
    if (isInternal(href)) {
        return (
            <Link href={href} prefetch="hover" viewTransition {...rest}>
                {children}
            </Link>
        );
    }
    return (
        <a href={href} {...rest}>
            {children}
        </a>
    );
}
