import { useEffect } from 'react';

const APP_NAME = 'SEO Audit Tool';

/** Updates the tab title; screen readers also announce it on navigation. */
export function useDocumentTitle(title) {
    useEffect(() => {
        document.title = title ? `${title} · ${APP_NAME}` : APP_NAME;
    }, [title]);
}
