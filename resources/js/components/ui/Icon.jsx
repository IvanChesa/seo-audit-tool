const PATHS = {
    check: 'M5 12.5l4.5 4.5L19 7.5',
    cross: 'M6.5 6.5l11 11M17.5 6.5l-11 11',
    alert: 'M12 4l9 16H3L12 4zM12 10v4.5M12 17.2v.3',
    info: 'M12 11v6M12 7.3v.3M12 21a9 9 0 110-18 9 9 0 010 18z',
    dash: 'M7 12h10',
    clock: 'M12 7v5l3 2M12 21a9 9 0 110-18 9 9 0 010 18z',
    external: 'M14 5h5v5M19 5l-8 8M17 14v5H5V7h5',
    trash: 'M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13',
    refresh: 'M19 12a7 7 0 11-2.05-4.95M19 4v4h-4',
    link: 'M10 14a4 4 0 005.66 0l3-3a4 4 0 00-5.66-5.66l-1 1M14 10a4 4 0 00-5.66 0l-3 3a4 4 0 005.66 5.66l1-1',
    search: 'M11 18a7 7 0 110-14 7 7 0 010 14zM20 20l-4-4',
    arrowLeft: 'M19 12H5M11 6l-6 6 6 6',
};

/**
 * Decorative inline SVG icon. Meaning is always also given by visible text,
 * so icons are hidden from assistive technology.
 */
function Icon({ name, className = '' }) {
    if (name === 'spinner') {
        return <span className={`spinner ${className}`} aria-hidden="true" />;
    }

    return (
        <svg
            className={`icon ${className}`}
            viewBox="0 0 24 24"
            width="1em"
            height="1em"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            <path d={PATHS[name] ?? PATHS.dash} />
        </svg>
    );
}

export default Icon;
