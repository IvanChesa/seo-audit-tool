import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createAudit, deleteAudit, listAudits } from '../api/audits';
import { httpError, page, summary } from '../test/fixtures';
import { renderRoute } from '../test/render';
import HistoryPage from './HistoryPage';

vi.mock('../api/audits', () => ({
    listAudits: vi.fn(),
    createAudit: vi.fn(),
    deleteAudit: vi.fn(),
}));

function renderHistory(route = '/history') {
    return renderRoute(<HistoryPage />, { path: '/history', route });
}

describe('HistoryPage', () => {
    beforeEach(() => {
        vi.mocked(listAudits).mockReset();
        vi.mocked(createAudit).mockReset();
        vi.mocked(deleteAudit).mockReset();
    });

    it('lists audits with their status and score', async () => {
        vi.mocked(listAudits).mockResolvedValue(
            page([summary(1), summary(2, { status: 'failed', score: null, score_rating: null })]),
        );

        renderHistory();

        const items = await screen.findAllByRole('listitem');
        expect(items).toHaveLength(2);
        expect(within(items[0]).getByRole('link', { name: 'site-1.example.com' })).toHaveAttribute(
            'href',
            '/audits/1',
        );
        expect(items[0]).toHaveTextContent('Estado: Completada');
        expect(items[0]).toHaveTextContent('Puntuación: 80/100');
        expect(items[1]).toHaveTextContent('Estado: Fallida');
        expect(items[1]).not.toHaveTextContent('Puntuación');
    });

    it('reads page and filters from the URL and paginates', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits).mockResolvedValue(
            page([summary(11)], { currentPage: 2, lastPage: 3, total: 21 }),
        );

        renderHistory('/history?page=2&status=completed&search=blog');

        expect(await screen.findByText('Página 2 de 3')).toBeInTheDocument();
        expect(listAudits).toHaveBeenCalledWith(
            { page: 2, perPage: 10, status: 'completed', search: 'blog' },
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(screen.getByLabelText('Buscar por URL')).toHaveValue('blog');
        expect(screen.getByLabelText('Estado')).toHaveValue('completed');

        await user.click(screen.getByRole('button', { name: 'Siguiente' }));

        expect(screen.getByTestId('location')).toHaveTextContent(
            '/history?page=3&status=completed&search=blog',
        );
        expect(listAudits).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 3 }),
            expect.anything(),
        );
    });

    it('applies filters from the form and resets the page', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits).mockResolvedValue(page([summary(1)]));

        renderHistory('/history?page=4');
        await screen.findAllByRole('listitem');

        await user.type(screen.getByLabelText('Buscar por URL'), 'tienda');
        await user.click(screen.getByRole('button', { name: 'Buscar' }));
        expect(screen.getByTestId('location')).toHaveTextContent('/history?search=tienda');

        await user.selectOptions(screen.getByLabelText('Estado'), 'failed');
        expect(screen.getByTestId('location')).toHaveTextContent(
            '/history?search=tienda&status=failed',
        );

        await user.click(screen.getByRole('button', { name: 'Quitar filtros' }));
        expect(screen.getByTestId('location')).toHaveTextContent(/^\/history$/);
    });

    it('distinguishes an empty history from a search without results', async () => {
        vi.mocked(listAudits).mockResolvedValue(page([]));

        const { unmount } = renderHistory();
        expect(await screen.findByText(/Todavía no hay auditorías/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Crea la primera' })).toHaveAttribute('href', '/');
        unmount();

        renderHistory('/history?search=nada');
        expect(
            await screen.findByText('No hay auditorías que coincidan con los filtros.'),
        ).toBeInTheDocument();
    });

    it('offers a way back when the requested page does not exist', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits).mockResolvedValue(
            page([], { currentPage: 9, lastPage: 2, total: 12 }),
        );

        renderHistory('/history?page=9');

        expect(await screen.findByText('Esta página del historial no existe.')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Ir a la primera página' }));
        expect(screen.getByTestId('location')).toHaveTextContent(/^\/history$/);
    });

    it('deletes an audit only after confirmation and refreshes the list', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits)
            .mockResolvedValueOnce(page([summary(1), summary(2)]))
            .mockResolvedValueOnce(page([summary(2)]));
        vi.mocked(deleteAudit).mockResolvedValue(undefined);

        renderHistory();
        await user.click(
            await screen.findByRole('button', {
                name: 'Eliminar la auditoría de site-1.example.com',
            }),
        );

        const dialog = screen.getByRole('dialog', { name: '¿Eliminar esta auditoría?' });
        expect(dialog).toHaveTextContent('site-1.example.com');
        await user.click(within(dialog).getByRole('button', { name: 'Eliminar' }));

        expect(deleteAudit).toHaveBeenCalledWith(1);
        expect(
            await screen.findByText('Se ha eliminado la auditoría de site-1.example.com.'),
        ).toBeInTheDocument();
        expect(await screen.findAllByRole('listitem')).toHaveLength(1);
    });

    it('repeats an audit from the list', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits).mockResolvedValue(page([summary(1)]));
        vi.mocked(createAudit).mockResolvedValue({ id: 99 });

        renderHistory();
        await user.click(
            await screen.findByRole('button', {
                name: 'Repetir la auditoría de site-1.example.com',
            }),
        );

        expect(createAudit).toHaveBeenCalledWith('https://site-1.example.com/');
        expect(screen.getByTestId('location')).toHaveTextContent('/audits/99');
    });

    it('shows an error with a retry button when the list cannot be loaded', async () => {
        const user = userEvent.setup();
        vi.mocked(listAudits)
            .mockRejectedValueOnce(httpError(500))
            .mockResolvedValueOnce(page([summary(1)]));

        renderHistory();

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('No se pudo cargar el historial');
        await user.click(within(alert).getByRole('button', { name: 'Reintentar' }));

        expect(await screen.findAllByRole('listitem')).toHaveLength(1);
    });
});
