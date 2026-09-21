/**
 * Human labels for the codes returned by the API. Every visual status in the
 * UI pairs a colour with an icon and a text, so meaning never depends on colour.
 */

export const AUDIT_STATUS = {
    pending: { label: 'En cola', tone: 'neutral', icon: 'clock' },
    processing: { label: 'Analizando', tone: 'info', icon: 'spinner' },
    completed: { label: 'Completada', tone: 'good', icon: 'check' },
    failed: { label: 'Fallida', tone: 'bad', icon: 'cross' },
};

export const AUDIT_STATUS_OPTIONS = Object.entries(AUDIT_STATUS).map(([value, { label }]) => ({
    value,
    label,
}));

export const SEVERITIES = ['critical', 'high', 'medium', 'low'];

export const SEVERITY = {
    critical: { label: 'Crítica', tone: 'bad', icon: 'cross' },
    high: { label: 'Alta', tone: 'bad', icon: 'alert' },
    medium: { label: 'Media', tone: 'warn', icon: 'alert' },
    low: { label: 'Baja', tone: 'neutral', icon: 'info' },
};

export const RATING = {
    good: { label: 'Bueno', tone: 'good', icon: 'check' },
    needs_improvement: { label: 'Mejorable', tone: 'warn', icon: 'alert' },
    poor: { label: 'Deficiente', tone: 'bad', icon: 'cross' },
};

export const CHECK_STATUS = {
    pass: { label: 'Correcto', tone: 'good', icon: 'check' },
    warning: { label: 'Mejorable', tone: 'warn', icon: 'alert' },
    fail: { label: 'Problema', tone: 'bad', icon: 'cross' },
    info: { label: 'Información', tone: 'neutral', icon: 'info' },
    unknown: { label: 'No comprobado', tone: 'neutral', icon: 'dash' },
};

export const STEP_STATUS = {
    pending: { label: 'Pendiente', tone: 'neutral', icon: 'dash' },
    running: { label: 'En curso', tone: 'info', icon: 'spinner' },
    completed: { label: 'Completado', tone: 'good', icon: 'check' },
    skipped: { label: 'Omitido', tone: 'neutral', icon: 'dash' },
    failed: { label: 'Fallido', tone: 'bad', icon: 'cross' },
    not_run: { label: 'No ejecutado', tone: 'neutral', icon: 'dash' },
};

export function lookup(table, key) {
    return table[key] ?? { label: key ?? '—', tone: 'neutral', icon: 'dash' };
}

export function isFinished(status) {
    return status === 'completed' || status === 'failed';
}
