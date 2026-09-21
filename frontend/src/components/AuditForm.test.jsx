import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createAudit } from '../api/audits';
import { httpError, networkError } from '../test/fixtures';
import AuditForm from './AuditForm';

vi.mock('../api/audits', () => ({ createAudit: vi.fn() }));

describe('AuditForm', () => {
    beforeEach(() => {
        vi.mocked(createAudit).mockReset();
    });

    it('has an accessible label and hint for the URL field', () => {
        render(<AuditForm onCreated={vi.fn()} />);

        const input = screen.getByLabelText('URL de la página');
        expect(input).toHaveAccessibleDescription(/Por ejemplo/);
        expect(input).toHaveAttribute('aria-invalid', 'false');
    });

    it('validates obvious mistakes without calling the API', async () => {
        const user = userEvent.setup();
        render(<AuditForm onCreated={vi.fn()} />);

        await user.click(screen.getByRole('button', { name: 'Analizar' }));

        expect(screen.getByRole('alert')).toHaveTextContent('Introduce la URL');
        expect(screen.getByLabelText('URL de la página')).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByLabelText('URL de la página')).toHaveAccessibleDescription(
            /Introduce la URL/,
        );
        expect(createAudit).not.toHaveBeenCalled();
    });

    it('normalises the URL, shows a busy state and reports the created audit', async () => {
        const user = userEvent.setup();
        const onCreated = vi.fn();
        let resolve;
        vi.mocked(createAudit).mockReturnValue(new Promise((done) => (resolve = done)));
        render(<AuditForm onCreated={onCreated} />);

        await user.type(screen.getByLabelText('URL de la página'), 'example.com');
        await user.click(screen.getByRole('button', { name: 'Analizar' }));

        expect(createAudit).toHaveBeenCalledWith('https://example.com');
        expect(screen.getByRole('button', { name: 'Creando auditoría…' })).toBeDisabled();

        resolve({ id: 5, status: 'pending' });
        await vi.waitFor(() =>
            expect(onCreated).toHaveBeenCalledWith({ id: 5, status: 'pending' }),
        );
    });

    it('shows the validation message returned by the API', async () => {
        const user = userEvent.setup();
        vi.mocked(createAudit).mockRejectedValue(
            httpError(422, {
                errors: {
                    url: [
                        'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.',
                    ],
                },
            }),
        );
        render(<AuditForm onCreated={vi.fn()} />);

        await user.type(screen.getByLabelText('URL de la página'), 'http://192.168.1.1');
        await user.click(screen.getByRole('button', { name: 'Analizar' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('dirección IP privada');
        expect(screen.getByRole('button', { name: 'Analizar' })).toBeEnabled();
    });

    it('explains rate limits and connection problems', async () => {
        const user = userEvent.setup();
        vi.mocked(createAudit)
            .mockRejectedValueOnce(
                httpError(429, { message: 'Has realizado demasiadas solicitudes.' }),
            )
            .mockRejectedValueOnce(networkError());
        render(<AuditForm onCreated={vi.fn()} />);

        await user.type(screen.getByLabelText('URL de la página'), 'https://example.com');
        await user.click(screen.getByRole('button', { name: 'Analizar' }));
        expect(await screen.findByRole('alert')).toHaveTextContent('demasiadas solicitudes');

        await user.click(screen.getByRole('button', { name: 'Analizar' }));
        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo conectar');
    });

    it('clears the error when the user edits the URL', async () => {
        const user = userEvent.setup();
        render(<AuditForm onCreated={vi.fn()} />);

        await user.click(screen.getByRole('button', { name: 'Analizar' }));
        await user.type(screen.getByLabelText('URL de la página'), 'e');

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});
