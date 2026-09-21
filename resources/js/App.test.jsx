import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { listAudits } from './api/audits';
import App from './App';
import { page } from './test/fixtures';

vi.mock('./api/audits', () => ({
    listAudits: vi.fn(),
    getAudit: vi.fn(),
    createAudit: vi.fn(),
    deleteAudit: vi.fn(),
}));

function renderApp(route = '/') {
    return render(
        <MemoryRouter initialEntries={[route]}>
            <App />
        </MemoryRouter>,
    );
}

describe('App', () => {
    beforeEach(() => {
        vi.mocked(listAudits).mockResolvedValue(page([]));
    });

    it('renders the home page with landmarks and a skip link', () => {
        renderApp();

        expect(screen.getByRole('link', { name: 'Saltar al contenido' })).toHaveAttribute(
            'href',
            '#contenido',
        );
        expect(screen.getByRole('banner')).toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveAttribute('id', 'contenido');
        expect(screen.getByRole('contentinfo')).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Audita el SEO');
        expect(screen.getByRole('link', { name: 'Nueva auditoría' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('navigates to the history and moves focus to the main content', async () => {
        const user = userEvent.setup();
        renderApp();

        await user.click(screen.getByRole('link', { name: 'Historial' }));

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Historial de auditorías' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Historial' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('main')).toHaveFocus();
        expect(document.title).toBe('Historial · SEO Audit Tool');
    });

    it('shows a not-found page for unknown routes', () => {
        renderApp('/no-existe');

        expect(screen.getByRole('heading', { name: 'Página no encontrada' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver al inicio' })).toHaveAttribute('href', '/');
    });
});
