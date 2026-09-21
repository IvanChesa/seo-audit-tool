import { act, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAudit, deleteAudit, getAudit } from '../api/audits';
import {
    completedAudit,
    failedAudit,
    httpError,
    legacyAudit,
    networkError,
    processingAudit,
} from '../test/fixtures';
import { renderRoute } from '../test/render';
import AuditPage from './AuditPage';

vi.mock('../api/audits', () => ({
    getAudit: vi.fn(),
    createAudit: vi.fn(),
    deleteAudit: vi.fn(),
}));

function renderAudit(id = '42') {
    return renderRoute(<AuditPage />, { path: '/audits/:id', route: `/audits/${id}` });
}

describe('AuditPage', () => {
    beforeEach(() => {
        vi.mocked(getAudit).mockReset();
        vi.mocked(createAudit).mockReset();
        vi.mocked(deleteAudit).mockReset();
    });

    describe('while the audit runs', () => {
        beforeEach(() => {
            vi.useFakeTimers({ shouldAdvanceTime: true });
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('shows the progress and switches to the report when it completes', async () => {
            vi.mocked(getAudit)
                .mockResolvedValueOnce(processingAudit())
                .mockResolvedValueOnce(completedAudit());

            renderAudit();

            expect(await screen.findByRole('progressbar')).toHaveAttribute(
                'aria-valuetext',
                '3 de 7 pasos completados',
            );
            expect(screen.getByText('Analizando', { selector: '.badge' })).toBeInTheDocument();

            await act(() => vi.advanceTimersByTimeAsync(2000));

            expect(
                await screen.findByRole('region', { name: 'Recomendaciones priorizadas' }),
            ).toBeInTheDocument();
            expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
            expect(document.title).toBe('Auditoría de example.com · SEO Audit Tool');
        });
    });

    it('explains why a failed audit could not be completed', async () => {
        vi.mocked(getAudit).mockResolvedValue(failedAudit());

        renderAudit();

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('No se pudo completar la auditoría');
        expect(alert).toHaveTextContent('código HTTP 404');
    });

    it('flags audits created by the previous version', async () => {
        vi.mocked(getAudit).mockResolvedValue(legacyAudit());

        renderAudit();

        expect(await screen.findByText('Informe de una versión anterior')).toBeInTheDocument();
        expect(screen.getByText('64')).toBeInTheDocument();
        expect(
            screen.queryByRole('region', { name: 'Recomendaciones priorizadas' }),
        ).not.toBeInTheDocument();
    });

    it('shows a not-found message for unknown or invalid ids', async () => {
        vi.mocked(getAudit).mockRejectedValue(httpError(404));

        renderAudit('999');
        expect(
            await screen.findByRole('heading', { name: 'Auditoría no encontrada' }),
        ).toBeInTheDocument();

        renderAudit('abc');
        expect(screen.getAllByRole('heading', { name: 'Auditoría no encontrada' })).toHaveLength(2);
        expect(getAudit).toHaveBeenCalledTimes(1);
    });

    it('retries server errors automatically, then offers a manual retry', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        vi.mocked(getAudit)
            .mockRejectedValueOnce(httpError(500))
            .mockRejectedValueOnce(httpError(500))
            .mockRejectedValueOnce(httpError(500))
            .mockResolvedValueOnce(completedAudit());

        renderAudit();
        // Two automatic retries with exponential backoff (4 s and 8 s) before giving up.
        await act(() => vi.advanceTimersByTimeAsync(12_000));

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('No se pudo cargar la auditoría');
        expect(getAudit).toHaveBeenCalledTimes(3);

        await user.click(within(alert).getByRole('button', { name: 'Reintentar' }));

        expect(
            await screen.findByRole('heading', { level: 1, name: 'example.com' }),
        ).toBeInTheDocument();
        vi.useRealTimers();
    });

    it('repeats the audit and navigates to the new one', async () => {
        const user = userEvent.setup();
        vi.mocked(getAudit).mockResolvedValue(completedAudit());
        vi.mocked(createAudit).mockResolvedValue({ id: 43, status: 'pending' });

        renderAudit();
        await user.click(await screen.findByRole('button', { name: 'Repetir auditoría' }));

        expect(createAudit).toHaveBeenCalledWith('https://example.com/');
        expect(screen.getByTestId('location')).toHaveTextContent('/audits/43');
    });

    it('asks for confirmation before deleting', async () => {
        const user = userEvent.setup();
        vi.mocked(getAudit).mockResolvedValue(completedAudit());
        vi.mocked(deleteAudit).mockResolvedValue(undefined);

        renderAudit();
        await user.click(await screen.findByRole('button', { name: 'Eliminar' }));

        const dialog = screen.getByRole('dialog', { name: '¿Eliminar esta auditoría?' });
        await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        expect(deleteAudit).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Eliminar' }));
        await user.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar' }),
        );

        expect(deleteAudit).toHaveBeenCalledWith(42);
        expect(screen.getByTestId('location')).toHaveTextContent('/history');
    });

    it('reports a failed deletion and stays on the page', async () => {
        const user = userEvent.setup();
        vi.mocked(getAudit).mockResolvedValue(completedAudit());
        vi.mocked(deleteAudit).mockRejectedValue(networkError());

        renderAudit();
        await user.click(await screen.findByRole('button', { name: 'Eliminar' }));
        await user.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar' }),
        );

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo conectar');
        expect(screen.getByTestId('location')).toHaveTextContent('/audits/42');
    });

    it('copies a shareable link to the audit', async () => {
        const user = userEvent.setup();
        const writeText = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue(undefined);
        vi.mocked(getAudit).mockResolvedValue(completedAudit());

        renderAudit();
        await user.click(await screen.findByRole('button', { name: 'Copiar enlace' }));

        expect(writeText).toHaveBeenCalledWith(window.location.href);
        expect(screen.getByText('Enlace copiado al portapapeles.')).toBeInTheDocument();
    });
});
