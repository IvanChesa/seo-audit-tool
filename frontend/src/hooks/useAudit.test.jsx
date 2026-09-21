import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { getAudit } from '../api/audits';
import { completedAudit, httpError, networkError, processingAudit } from '../test/fixtures';
import { useAudit } from './useAudit';

vi.mock('../api/audits', () => ({ getAudit: vi.fn() }));

describe('useAudit', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.mocked(getAudit).mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('polls while the audit is running and stops when it completes', async () => {
        vi.mocked(getAudit)
            .mockResolvedValueOnce(processingAudit())
            .mockResolvedValueOnce(processingAudit())
            .mockResolvedValueOnce(completedAudit());

        const { result } = renderHook(() => useAudit('42', { interval: 1000 }));

        await act(() => vi.advanceTimersByTimeAsync(0));
        expect(result.current.status).toBe('success');
        expect(result.current.audit.status).toBe('processing');

        await act(() => vi.advanceTimersByTimeAsync(1000));
        await act(() => vi.advanceTimersByTimeAsync(1000));
        expect(result.current.audit.status).toBe('completed');
        expect(getAudit).toHaveBeenCalledTimes(3);

        // Finished: no more requests.
        await act(() => vi.advanceTimersByTimeAsync(10_000));
        expect(getAudit).toHaveBeenCalledTimes(3);
    });

    it('does not poll an audit that is already finished', async () => {
        vi.mocked(getAudit).mockResolvedValue(completedAudit({ status: 'failed' }));

        renderHook(() => useAudit('42', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(5000));

        expect(getAudit).toHaveBeenCalledTimes(1);
    });

    it('does not poll legacy audits, whose jobs no longer exist', async () => {
        vi.mocked(getAudit).mockResolvedValue(processingAudit({ legacy: true }));

        renderHook(() => useAudit('7', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(5000));

        expect(getAudit).toHaveBeenCalledTimes(1);
    });

    it('stops polling and aborts the request when unmounted', async () => {
        vi.mocked(getAudit).mockResolvedValue(processingAudit());

        const { unmount } = renderHook(() => useAudit('42', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(0));
        const { signal } = vi.mocked(getAudit).mock.calls[0][1];

        unmount();
        await act(() => vi.advanceTimersByTimeAsync(10_000));

        expect(signal.aborted).toBe(true);
        expect(getAudit).toHaveBeenCalledTimes(1);
    });

    it('retries transient network errors with backoff, then reports them', async () => {
        vi.mocked(getAudit).mockRejectedValue(networkError());

        const { result } = renderHook(() => useAudit('42', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(0));
        expect(result.current.status).toBe('loading');

        await act(() => vi.advanceTimersByTimeAsync(2000)); // 2nd attempt after 2 s
        await act(() => vi.advanceTimersByTimeAsync(4000)); // 3rd attempt after 4 s

        expect(getAudit).toHaveBeenCalledTimes(3);
        expect(result.current.status).toBe('error');
        expect(result.current.error.kind).toBe('network');
    });

    it('stops immediately when the audit does not exist', async () => {
        vi.mocked(getAudit).mockRejectedValue(httpError(404));

        const { result } = renderHook(() => useAudit('999', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(10_000));

        expect(getAudit).toHaveBeenCalledTimes(1);
        expect(result.current.error.kind).toBe('not_found');
    });

    it('can be reloaded after an error', async () => {
        vi.mocked(getAudit)
            .mockRejectedValueOnce(httpError(404))
            .mockResolvedValueOnce(completedAudit());

        const { result } = renderHook(() => useAudit('42', { interval: 1000 }));
        await act(() => vi.advanceTimersByTimeAsync(0));
        expect(result.current.status).toBe('error');

        act(() => result.current.reload());
        await act(() => vi.advanceTimersByTimeAsync(0));

        expect(result.current.status).toBe('success');
        expect(result.current.audit.id).toBe(42);
    });
});
