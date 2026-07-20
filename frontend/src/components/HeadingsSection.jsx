import IssueList from './IssueList';

// Árbol visual de encabezados: cada nivel se indenta según su profundidad.
function HeadingsSection({ result }) {
    const { headings = [], counts = {}, issues = [], error } = result.data;

    if (error) {
        return <p className="section-error">No se pudo analizar los encabezados ({error}).</p>;
    }

    return (
        <div>
            <div className="headings-counts">
                {Object.entries(counts).map(([level, count]) => (
                    <span key={level} className={`heading-count ${count > 0 ? 'active' : ''}`}>
                        {level.toUpperCase()}: {count}
                    </span>
                ))}
            </div>

            <ol className="headings-tree">
                {headings.map((heading, index) => (
                    <li
                        key={index}
                        style={{ paddingLeft: `${(heading.level - 1) * 22}px` }}
                    >
                        <span className={`heading-badge level-${heading.level}`}>
                            H{heading.level}
                        </span>
                        <span className="heading-text">{heading.text || <em>(vacío)</em>}</span>
                    </li>
                ))}
            </ol>

            <IssueList issues={issues} />
        </div>
    );
}

export default HeadingsSection;
