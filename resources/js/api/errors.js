import { isCancel } from 'axios';

/**
 * Turns any error thrown by the API client into something the UI can show:
 * a kind (to decide what to render), a user-facing message in Spanish and,
 * for validation errors, the messages per field.
 */
export function describeApiError(error) {
    if (isCancel(error)) {
        return { kind: 'cancelled', status: null, message: '', fieldErrors: {} };
    }

    const response = error?.response;

    if (!response) {
        return {
            kind: 'network',
            status: null,
            message:
                'No se pudo conectar con el servidor. Comprueba tu conexión o que la API esté en marcha.',
            fieldErrors: {},
        };
    }

    const { status, data } = response;
    const serverMessage = typeof data?.message === 'string' ? data.message : '';

    if (status === 422) {
        const fieldErrors = Object.fromEntries(
            Object.entries(data?.errors ?? {}).map(([field, messages]) => [
                field,
                Array.isArray(messages) ? messages[0] : String(messages),
            ]),
        );

        return {
            kind: 'validation',
            status,
            message: Object.values(fieldErrors)[0] ?? 'Revisa los datos introducidos.',
            fieldErrors,
        };
    }

    if (status === 429) {
        return {
            kind: 'rate_limit',
            status,
            message:
                serverMessage ||
                'Has realizado demasiadas solicitudes. Espera un momento y vuelve a intentarlo.',
            fieldErrors: {},
        };
    }

    if (status === 404) {
        return {
            kind: 'not_found',
            status,
            message: 'El recurso solicitado no existe o ha sido eliminado.',
            fieldErrors: {},
        };
    }

    return {
        kind: 'server',
        status,
        message: 'El servidor no pudo completar la solicitud. Inténtalo de nuevo en unos minutos.',
        fieldErrors: {},
    };
}
