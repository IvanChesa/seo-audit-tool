import { describe, expect, it } from 'vitest';
import { formatDateTime, formatDuration, formatNumber } from './format';

describe('format helpers', () => {
    it('formats dates and tolerates missing or invalid values', () => {
        expect(formatDateTime('2026-09-21T10:00:00Z')).toMatch(/2026/);
        expect(formatDateTime(null)).toBe('—');
        expect(formatDateTime('not a date')).toBe('—');
    });

    it('formats numbers with Spanish conventions', () => {
        expect(formatNumber(12345)).toBe('12.345');
        expect(formatNumber(2.5, { maximumFractionDigits: 1 })).toBe('2,5');
        expect(formatNumber(null)).toBe('—');
    });

    it('formats durations between two dates', () => {
        expect(formatDuration('2026-09-21T10:00:00Z', '2026-09-21T10:00:42Z')).toBe('42 s');
        expect(formatDuration('2026-09-21T10:00:00Z', '2026-09-21T10:02:05Z')).toBe('2 min 5 s');
        expect(formatDuration('2026-09-21T10:00:00Z', '2026-09-21T10:03:00Z')).toBe('3 min');
        expect(formatDuration(null, '2026-09-21T10:00:00Z')).toBeNull();
    });
});
