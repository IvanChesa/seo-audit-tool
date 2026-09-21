import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { completedAudit, sections } from '../../test/fixtures';
import AuditReport from './AuditReport';

describe('AuditReport', () => {
    it('summarises the score with text, not only colour', () => {
        render(<AuditReport audit={completedAudit()} />);

        const summary = screen.getByRole('region', { name: 'Resumen' });
        expect(within(summary).getByText('87')).toBeInTheDocument();
        expect(within(summary).getByText('Mejorable')).toBeInTheDocument();
        expect(
            within(summary).getByText('Se han detectado 4 aspectos a mejorar.'),
        ).toBeInTheDocument();
        expect(within(summary).getByText('Alta: 2')).toBeInTheDocument();
    });

    it('lists recommendations by severity with evidence and fix, and filters them', async () => {
        const user = userEvent.setup();
        render(<AuditReport audit={completedAudit()} />);

        const recommendations = screen.getByRole('region', { name: 'Recomendaciones priorizadas' });
        const items = within(recommendations).getAllByRole('listitem');
        expect(items).toHaveLength(4);
        expect(items[0]).toHaveTextContent('Severidad: Alta');
        expect(items[0]).toHaveTextContent('La página no tiene un encabezado H1');
        expect(items[0]).toHaveTextContent('Detectado: Hay 4 encabezados');
        expect(items[0]).toHaveTextContent('Cómo solucionarlo:');

        await user.click(within(recommendations).getByRole('button', { name: 'Baja (1)' }));

        expect(within(recommendations).getByRole('button', { name: 'Baja (1)' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(within(recommendations).getAllByRole('listitem')).toHaveLength(1);
        expect(
            within(recommendations).getByText('Faltan etiquetas Open Graph'),
        ).toBeInTheDocument();
        expect(within(recommendations).getByRole('button', { name: 'Crítica (0)' })).toBeDisabled();
    });

    it('renders one section per analysis with its metrics explained', () => {
        render(<AuditReport audit={completedAudit()} />);

        const technical = screen.getByRole('region', { name: 'Técnico' });
        expect(
            within(technical).getByRole('table', { name: 'Métricas de Técnico' }),
        ).toBeInTheDocument();
        expect(within(technical).getByText(/HTTPS cifra la conexión/)).toBeInTheDocument();
        expect(
            within(technical).getByText('Puntuación de la sección:', { exact: false }),
        ).toBeInTheDocument();

        const links = screen.getByRole('region', { name: 'Enlaces' });
        expect(within(links).getByRole('link', { name: /example\.com\/roto/ })).toHaveAttribute(
            'target',
            '_blank',
        );
        expect(within(links).getByText('HTTP 404')).toBeInTheDocument();
    });

    it('makes clear that PageSpeed was not run and does not count', () => {
        render(<AuditReport audit={completedAudit()} />);

        const performance = screen.getByRole('region', { name: 'Rendimiento' });
        expect(
            within(performance).getByText('Esta comprobación no se ha ejecutado'),
        ).toBeInTheDocument();
        expect(within(performance).getByText(/PAGESPEED_API_KEY/)).toBeInTheDocument();
        expect(within(performance).queryByRole('table')).not.toBeInTheDocument();

        const breakdown = screen.getByRole('region', { name: 'Cómo se calcula la puntuación' });
        const row = within(breakdown).getByRole('row', { name: /Rendimiento/ });
        expect(row).toHaveTextContent('No cuenta');
    });

    it('explains when the critical cap lowered the score', () => {
        render(
            <AuditReport
                audit={completedAudit({
                    score: 49,
                    score_rating: 'poor',
                    score_breakdown: {
                        ...completedAudit().score_breakdown,
                        weighted_average: 88,
                        critical_cap_applied: true,
                    },
                })}
            />,
        );

        expect(screen.getByText(/se limita a 49\. Sin ese límite sería 88/)).toBeInTheDocument();
    });

    it('shows failed sections with their reason', () => {
        const withFailure = sections().map((section) =>
            section.key === 'links'
                ? {
                      ...section,
                      status: 'failed',
                      score: null,
                      data: {},
                      issues: [],
                      error: {
                          code: 'analysis_failed',
                          message: 'No se pudo completar el análisis de «Enlaces».',
                      },
                  }
                : section,
        );

        render(<AuditReport audit={completedAudit({ sections: withFailure })} />);

        const links = screen.getByRole('region', { name: 'Enlaces' });
        expect(within(links).getByText('No se pudo completar este análisis')).toBeInTheDocument();
        expect(within(links).getByText(/análisis de «Enlaces»/)).toBeInTheDocument();
    });

    it('survives incomplete or unexpected data from the API', () => {
        const partial = completedAudit({
            issues: undefined,
            issues_summary: undefined,
            score_breakdown: undefined,
            sections: [
                {
                    key: 'meta',
                    label: 'Metadatos',
                    status: 'completed',
                    score: 90,
                    score_rating: 'good',
                    data: {},
                    issues: [],
                },
                {
                    key: 'content',
                    label: 'Contenido',
                    status: 'completed',
                    score: 90,
                    score_rating: 'good',
                    data: { checks: null, top_terms: 'x' },
                    issues: [],
                },
                {
                    key: 'unknown',
                    label: 'Sección nueva',
                    status: 'completed',
                    score: 50,
                    score_rating: 'needs_improvement',
                    data: {},
                    issues: [],
                },
            ],
        });

        render(<AuditReport audit={partial} />);

        expect(screen.getByRole('region', { name: 'Metadatos' })).toBeInTheDocument();
        expect(screen.getByRole('region', { name: 'Sección nueva' })).toBeInTheDocument();
        const recommendations = screen.getByRole('region', { name: 'Recomendaciones priorizadas' });
        expect(
            within(recommendations).getByText(
                'No se han detectado problemas en las comprobaciones realizadas.',
            ),
        ).toBeInTheDocument();
    });
});
