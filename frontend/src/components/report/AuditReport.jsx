import { formatDateTime, formatDuration } from '../../lib/format';
import { SEVERITIES, SEVERITY } from '../../lib/labels';
import ScoreGauge from '../ScoreGauge';
import Badge from '../ui/Badge';
import ContentDetails from './details/ContentDetails';
import HeadingsDetails from './details/HeadingsDetails';
import LinksDetails from './details/LinksDetails';
import MetaDetails from './details/MetaDetails';
import PerformanceDetails from './details/PerformanceDetails';
import TechnicalDetails from './details/TechnicalDetails';
import PriorityIssues from './PriorityIssues';
import ReportSection from './ReportSection';
import ScoreBreakdown from './ScoreBreakdown';

const DETAILS = {
    technical: TechnicalDetails,
    meta: MetaDetails,
    headings: HeadingsDetails,
    content: ContentDetails,
    links: LinksDetails,
    performance: PerformanceDetails,
};

function issueSummaryText(total) {
    if (total === 0) return 'No se han detectado problemas en las comprobaciones realizadas.';
    if (total === 1) return 'Se ha detectado 1 aspecto a mejorar.';

    return `Se han detectado ${total} aspectos a mejorar.`;
}

/**
 * Report of a completed audit: summary, prioritised recommendations, score
 * breakdown and one card per section.
 */
function AuditReport({ audit }) {
    const sections = Array.isArray(audit.sections) ? audit.sections : [];
    const summary = audit.issues_summary ?? {};
    const duration = formatDuration(audit.started_at, audit.finished_at);

    return (
        <div className="report">
            <section className="card report-summary" aria-labelledby="summary-title">
                <h2 id="summary-title" className="visually-hidden">
                    Resumen
                </h2>
                <ScoreGauge score={audit.score} rating={audit.score_rating} />
                <div className="report-summary__details">
                    <p className="report-summary__lead">{issueSummaryText(summary.total ?? 0)}</p>
                    <ul className="severity-counts" aria-label="Problemas por severidad">
                        {SEVERITIES.map((severity) => (
                            <li key={severity}>
                                <Badge
                                    tone={summary[severity] ? SEVERITY[severity].tone : 'neutral'}
                                    icon={SEVERITY[severity].icon}
                                    label={`${SEVERITY[severity].label}: ${summary[severity] ?? 0}`}
                                />
                            </li>
                        ))}
                    </ul>
                    <dl className="facts">
                        <div>
                            <dt>URL analizada</dt>
                            <dd className="mono break">{audit.final_url ?? audit.url}</dd>
                        </div>
                        <div>
                            <dt>Respuesta HTTP</dt>
                            <dd>{audit.http_status ?? '—'}</dd>
                        </div>
                        <div>
                            <dt>Finalizada</dt>
                            <dd>
                                {formatDateTime(audit.finished_at)}
                                {duration && ` (${duration})`}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <nav className="section-nav" aria-label="Secciones del informe">
                <ul>
                    <li>
                        <a href="#priority-issues-title">Recomendaciones</a>
                    </li>
                    {sections.map((section) => (
                        <li key={section.key}>
                            <a href={`#seccion-${section.key}`}>{section.label}</a>
                        </li>
                    ))}
                </ul>
            </nav>

            <PriorityIssues issues={audit.issues ?? []} summary={summary} />
            <ScoreBreakdown breakdown={audit.score_breakdown} />

            {sections.map((section) => {
                const Details = DETAILS[section.key];

                return (
                    <ReportSection key={section.key} section={section}>
                        {Details && <Details data={section.data ?? {}} />}
                    </ReportSection>
                );
            })}
        </div>
    );
}

export default AuditReport;
