import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { progress } from '../test/fixtures';
import AnalysisProgress from './AnalysisProgress';
import ErrorBoundary from './ErrorBoundary';
import ScoreGauge from './ScoreGauge';
import ConfirmDialog from './ui/ConfirmDialog';

describe('ScoreGauge', () => {
    it('shows the score and the rating as text', () => {
        render(<ScoreGauge score={42} rating="poor" />);

        expect(screen.getByText('42')).toBeInTheDocument();
        expect(screen.getByText('/100')).toBeInTheDocument();
        expect(screen.getByText('Deficiente')).toBeInTheDocument();
    });

    it('handles a missing score', () => {
        render(<ScoreGauge score={null} rating={null} />);

        expect(screen.getByText('—')).toBeInTheDocument();
    });
});

describe('AnalysisProgress', () => {
    it('exposes the progress to assistive technology', () => {
        render(
            <AnalysisProgress
                status="processing"
                progress={progress({ links: 'running', performance: 'failed' })}
            />,
        );

        const bar = screen.getByRole('progressbar', { name: 'Progreso de la auditoría' });
        expect(bar).toHaveAttribute('aria-valuenow', '86');
        expect(bar).toHaveAttribute('aria-valuetext', '6 de 7 pasos completados');
        expect(screen.getByText('Enlaces').parentElement).toHaveTextContent('En curso');
        expect(screen.getByText('Rendimiento').parentElement).toHaveTextContent('Fallido');
    });

    it('explains that a pending audit is queued', () => {
        render(<AnalysisProgress status="pending" progress={progress({ fetch: 'pending' })} />);

        expect(screen.getByRole('heading', { name: /En cola/ })).toBeInTheDocument();
    });
});

function Broken() {
    throw new Error('Unexpected payload');
}

describe('ErrorBoundary', () => {
    it('replaces a crashed tree with a recoverable message', () => {
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(
            <ErrorBoundary>
                <Broken />
            </ErrorBoundary>,
        );

        expect(screen.getByRole('alert')).toHaveTextContent('Algo ha salido mal');
        expect(screen.getByRole('button', { name: 'Recargar la página' })).toBeInTheDocument();
        expect(screen.queryByText('Unexpected payload')).not.toBeInTheDocument();
        expect(consoleError).toHaveBeenCalled();
    });
});

describe('ConfirmDialog', () => {
    it('is a labelled modal that focuses the safe option first', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();
        const onCancel = vi.fn();

        render(
            <ConfirmDialog
                open
                title="¿Eliminar?"
                confirmLabel="Eliminar"
                onConfirm={onConfirm}
                onCancel={onCancel}
            >
                <p>No se puede deshacer.</p>
            </ConfirmDialog>,
        );

        const dialog = screen.getByRole('dialog', { name: '¿Eliminar?' });
        expect(dialog).toHaveAccessibleDescription('No se puede deshacer.');
        expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus();

        await user.click(screen.getByRole('button', { name: 'Eliminar' }));
        expect(onConfirm).toHaveBeenCalledOnce();

        await user.click(screen.getByRole('button', { name: 'Cancelar' }));
        expect(onCancel).toHaveBeenCalledOnce();
    });

    it('disables its buttons while the action runs', () => {
        render(
            <ConfirmDialog open busy title="¿Eliminar?" onConfirm={vi.fn()} onCancel={vi.fn()}>
                <p>Texto</p>
            </ConfirmDialog>,
        );

        expect(screen.getByRole('button', { name: 'Procesando…' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled();
    });
});
