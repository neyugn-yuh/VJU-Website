/** Backend URLs are site-relative ("/path/") for internal targets, absolute for external ones. */
export const isInternal = (url: string) => url.startsWith('/') && !url.startsWith('//');
