import { useCallback, useEffect, useState } from 'react';
import { getAudit } from '../api/audits';
import { describeApiError } from '../api/errors';
import { isFinished } from '../lib/labels';

export const POLL_INTERVAL_MS = 2000;
const MAX_CONSECUTIVE_FAILURES = 3;

/**
 * Loads an audit and keeps polling it while it is pending or processing.
 *
 * - Requests never overlap: the next one is scheduled when the previous ends.
 * - Polling stops when the audit finishes, on a 404, after repeated network
 *   errors (with exponential backoff between them) and on unmount.
 * - `replace` updates the audit locally (e.g. after an action) without a request.
 */
export function useAudit(id, { interval = POLL_INTERVAL_MS } = {}) {
    const [state, setState] = useState({ status: 'loading', audit: null, error: null });
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        let timer = null;
        let failures = 0;
        let stopped = false;

        const schedule = (delay) => {
            if (!stopped) timer = setTimeout(load, delay);
        };

        async function load() {
            try {
                const audit = await getAudit(id, { signal: controller.signal });
                if (stopped) return;

                failures = 0;
                setState({ status: 'success', audit, error: null });

                if (!isFinished(audit.status)) schedule(interval);
            } catch (error) {
                const info = describeApiError(error);
                if (stopped || info.kind === 'cancelled') return;

                failures += 1;
                const retryable =
                    info.kind === 'network' || info.kind === 'server' || info.kind === 'rate_limit';

                if (retryable && failures < MAX_CONSECUTIVE_FAILURES) {
                    schedule(interval * 2 ** failures);
                    return;
                }

                // Keep the last known audit on screen, but report the problem.
                setState((current) => ({ ...current, status: 'error', error: info }));
            }
        }

        setState({ status: 'loading', audit: null, error: null });
        load();

        return () => {
            stopped = true;
            controller.abort();
            clearTimeout(timer);
        };
    }, [id, interval, attempt]);

    const reload = useCallback(() => setAttempt((value) => value + 1), []);

    return { ...state, reload };
}
