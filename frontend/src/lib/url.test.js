import { describe, expect, it } from 'vitest';
import { MAX_URL_LENGTH, hostnameOf, prepareUrl } from './url';

describe('prepareUrl', () => {
    it.each([
        ['https://example.com/blog', 'https://example.com/blog'],
        ['  http://example.com  ', 'http://example.com'],
        ['example.com', 'https://example.com'],
        ['example.com:8080/page', 'https://example.com:8080/page'],
        ['sub.dominio.es/ruta?x=1', 'https://sub.dominio.es/ruta?x=1'],
    ])('accepts %s', (input, expected) => {
        expect(prepareUrl(input)).toEqual({ url: expected, error: null });
    });

    it.each([
        ['', 'Introduce la URL'],
        ['   ', 'Introduce la URL'],
        ['https://exa mple.com', 'espacios'],
        ['ftp://example.com', 'http:// o https://'],
        ['javascript:alert(1)', 'http:// o https://'],
        ['mailto:hola@example.com', 'http:// o https://'],
        ['https://user:pass@example.com', 'usuario ni contraseña'],
        ['localhost', 'dominio completo'],
        ['https://', 'formato válido'],
        [`https://example.com/${'a'.repeat(MAX_URL_LENGTH)}`, `${MAX_URL_LENGTH} caracteres`],
    ])('rejects %j', (input, message) => {
        const result = prepareUrl(input);

        expect(result.url).toBeNull();
        expect(result.error).toContain(message);
    });

    it('treats null and undefined as empty', () => {
        expect(prepareUrl(undefined).error).toContain('Introduce la URL');
        expect(prepareUrl(null).error).toContain('Introduce la URL');
    });
});

describe('hostnameOf', () => {
    it('returns the hostname or the original text', () => {
        expect(hostnameOf('https://www.example.com/a?b=1')).toBe('www.example.com');
        expect(hostnameOf('no es una url')).toBe('no es una url');
    });
});
