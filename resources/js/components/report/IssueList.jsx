import { SEVERITY, lookup } from '../../lib/labels';
import Badge from '../ui/Badge';

/**
 * Problems with their severity, the evidence found and how to fix them.
 */
function IssueList({
    issues,
    showSection = false,
    headingLevel = 3,
    emptyMessage = 'No se han detectado problemas.',
}) {
    const Title = `h${headingLevel}`;

    if (!issues || issues.length === 0) {
        return <p className="empty-note">{emptyMessage}</p>;
    }

    return (
        <ul className="issues">
            {issues.map((issue, index) => {
                const severity = lookup(SEVERITY, issue.severity);

                return (
                    <li
                        key={`${issue.section ?? ''}-${issue.code}-${index}`}
                        className={`issue issue--${issue.severity}`}
                    >
                        <div className="issue__header">
                            <Badge
                                tone={severity.tone}
                                icon={severity.icon}
                                label={severity.label}
                                srPrefix="Severidad:"
                            />
                            {showSection && issue.section_label && (
                                <span className="issue__section">{issue.section_label}</span>
                            )}
                        </div>
                        <Title className="issue__title">{issue.title}</Title>
                        {issue.evidence && (
                            <p className="issue__evidence">
                                <strong>Detectado: </strong>
                                {issue.evidence}
                            </p>
                        )}
                        {issue.recommendation && (
                            <p className="issue__recommendation">
                                <strong>Cómo solucionarlo: </strong>
                                {issue.recommendation}
                            </p>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export default IssueList;
