import { CanceledError } from 'axios';
import { describe, expect, it } from 'vitest';
import { httpError, networkError } from '../test/fixtures';
import { describeApiError } from './errors';

describe('describeApiError', () => {
    it('extracts the first message of each invalid field', () => {
        const info = describeApiError(
            httpError(422, {
                message: 'The url field is invalid.',
                errors: { url: ['La URL apunta a una dirección IP privada.', 'Otro error'] },
            }),
        );

        expect(info.kind).toBe('validation');
        expect(info.fieldErrors).toEqual({ url: 'La URL apunta a una dirección IP privada.' });
        expect(info.message).toBe('La URL apunta a una dirección IP privada.');
    });

    it('uses the server message for rate limits', () => {
        const info = describeApiError(
            httpError(429, { message: 'Has realizado demasiadas solicitudes.' }),
        );

        expect(info).toMatchObject({
            kind: 'rate_limit',
            status: 429,
            message: 'Has realizado demasiadas solicitudes.',
        });
    });

    it('recognises missing resources', () => {
        expect(
            describeApiError(httpError(404, { message: 'El recurso solicitado no existe.' })).kind,
        ).toBe('not_found');
    });

    it('never shows raw server errors to the user', () => {
        const info = describeApiError(
            httpError(500, { message: 'SQLSTATE[HY000] Connection refused' }),
        );

        expect(info.kind).toBe('server');
        expect(info.message).not.toContain('SQLSTATE');
    });

    it('explains network failures', () => {
        const info = describeApiError(networkError());

        expect(info.kind).toBe('network');
        expect(info.message).toContain('No se pudo conectar');
    });

    it('marks cancelled requests so they can be ignored', () => {
        expect(describeApiError(new CanceledError()).kind).toBe('cancelled');
    });
});
