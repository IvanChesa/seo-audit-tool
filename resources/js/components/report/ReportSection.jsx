import { SECTION_HELP } from '../../lib/help';
import { RATING, lookup } from '../../lib/labels';
import Alert from '../ui/Alert';
import Badge from '../ui/Badge';
import CheckTable from './CheckTable';
import IssueList from './IssueList';

/**
 * One section of the report. Handles the three outcomes returned by the API:
 * completed (checks + details + issues), skipped (e.g. PageSpeed without an
 * API key) and failed / not run.
 */
function ReportSection({ section, children }) {
    const titleId = `section-${section.key}-title`;
    const rating = lookup(RATING, section.score_rating);
    const checks = section.data?.checks;

    return (
        <section
            id={`seccion-${section.key}`}
            className="card report-section"
            aria-labelledby={titleId}
        >
            <header className="report-section__header">
                <h2 id={titleId} className="card__title">
                    {section.label}
                </h2>
                {section.status === 'completed' &&
                    section.score !== null &&
                    section.score !== undefined && (
                        <Badge
                            tone={rating.tone}
                            icon={rating.icon}
                            label={`${section.score}/100 · ${rating.label}`}
                            srPrefix="Puntuación de la sección:"
                        />
                    )}
            </header>
            {SECTION_HELP[section.key] && (
                <p className="card__intro">{SECTION_HELP[section.key]}</p>
            )}

            {section.status === 'skipped' && (
                <Alert type="info" title="Esta comprobación no se ha ejecutado">
                    <p>{section.error?.message ?? 'La sección se ha omitido.'}</p>
                    <p>No cuenta para la puntuación global.</p>
                </Alert>
            )}

            {(section.status === 'failed' || section.status === 'not_run') && (
                <Alert type="warning" title="No se pudo completar este análisis">
                    <p>
                        {section.error?.message ??
                            'El análisis de esta sección no llegó a ejecutarse.'}
                    </p>
                    <p>No cuenta para la puntuación global.</p>
                </Alert>
            )}

            {section.status === 'completed' && (
                <>
                    <CheckTable checks={checks} caption={`Métricas de ${section.label}`} />
                    {children}
                    <h3 className="subheading">Problemas detectados</h3>
                    <IssueList issues={section.issues} headingLevel={4} />
                </>
            )}
        </section>
    );
}

export default ReportSection;
