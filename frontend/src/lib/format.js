const dateTimeFormatter = new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const numberFormatter = new Intl.NumberFormat('es-ES');

export function formatDateTime(isoString) {
    if (!isoString) return '—';

    const date = new Date(isoString);

    return Number.isNaN(date.getTime()) ? '—' : dateTimeFormatter.format(date);
}

export function formatNumber(value, options) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';

    return options
        ? new Intl.NumberFormat('es-ES', options).format(value)
        : numberFormatter.format(value);
}

/** Duration between two ISO dates, e.g. "42 s" or "2 min 5 s". */
export function formatDuration(startIso, endIso) {
    if (!startIso || !endIso) return null;

    const seconds = Math.round((new Date(endIso) - new Date(startIso)) / 1000);

    if (!Number.isFinite(seconds) || seconds < 0) return null;
    if (seconds < 60) return `${seconds} s`;

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return rest === 0 ? `${minutes} min` : `${minutes} min ${rest} s`;
}
