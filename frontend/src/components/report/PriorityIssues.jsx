import { useState } from 'react';
import { SEVERITIES, SEVERITY } from '../../lib/labels';
import IssueList from './IssueList';

/**
 * Every issue of the report ordered by severity, filterable by severity.
 */
function PriorityIssues({ issues = [], summary }) {
    const [filter, setFilter] = useState('all');
    const visible = filter === 'all' ? issues : issues.filter((issue) => issue.severity === filter);
    const counts = summary ?? {};

    return (
        <section className="card" aria-labelledby="priority-issues-title">
            <h2 id="priority-issues-title" className="card__title">
                Recomendaciones priorizadas
            </h2>
            <p className="card__intro">
                Empieza por los problemas críticos y de severidad alta: son los que más afectan a la
                visibilidad de la página.
            </p>

            {issues.length > 0 && (
                <div className="filter-group" role="group" aria-label="Filtrar por severidad">
                    <button
                        type="button"
                        className="chip"
                        aria-pressed={filter === 'all'}
                        onClick={() => setFilter('all')}
                    >
                        Todas ({issues.length})
                    </button>
                    {SEVERITIES.map((severity) => (
                        <button
                            key={severity}
                            type="button"
                            className={`chip chip--${SEVERITY[severity].tone}`}
                            aria-pressed={filter === severity}
                            onClick={() => setFilter(severity)}
                            disabled={!counts[severity]}
                        >
                            {SEVERITY[severity].label} ({counts[severity] ?? 0})
                        </button>
                    ))}
                </div>
            )}

            <IssueList
                issues={visible}
                showSection
                emptyMessage={
                    issues.length === 0
                        ? 'No se han detectado problemas en las comprobaciones realizadas.'
                        : 'No hay problemas con esta severidad.'
                }
            />
        </section>
    );
}

export default PriorityIssues;
