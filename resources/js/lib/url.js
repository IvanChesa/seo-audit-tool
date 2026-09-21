export const MAX_URL_LENGTH = 2048;

/**
 * Quick client-side check so obvious mistakes get instant feedback. The API
 * is the source of truth: it also resolves DNS and blocks private or
 * internal addresses, which a browser cannot verify.
 *
 * @returns {{ url: string, error: null } | { url: null, error: string }}
 */
export function prepareUrl(input) {
    const value = (input ?? '').trim();

    if (value === '') {
        return { url: null, error: 'Introduce la URL de la página que quieres auditar.' };
    }

    if (value.length > MAX_URL_LENGTH) {
        return { url: null, error: `La URL no puede superar los ${MAX_URL_LENGTH} caracteres.` };
    }

    if (/\s/.test(value)) {
        return { url: null, error: 'La URL no puede contener espacios.' };
    }

    // "example.com" or "example.com:8080/page" → https://…; other schemes are kept to reject them.
    const hasScheme = /^[a-z][a-z0-9+.-]*:(?!\d)/i.test(value);
    const candidate = hasScheme ? value : `https://${value}`;

    let parsed;
    try {
        parsed = new URL(candidate);
    } catch {
        return { url: null, error: 'La URL no tiene un formato válido.' };
    }

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return {
            url: null,
            error: 'Solo se admiten direcciones que empiecen por http:// o https://.',
        };
    }

    if (parsed.username || parsed.password) {
        return { url: null, error: 'La URL no puede incluir usuario ni contraseña.' };
    }

    if (!parsed.hostname.includes('.') && !parsed.hostname.startsWith('[')) {
        return { url: null, error: 'Escribe un dominio completo, por ejemplo ejemplo.com.' };
    }

    return { url: candidate, error: null };
}

/** Hostname for display, or the original string if it is not a valid URL. */
export function hostnameOf(url) {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}
