const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

function HeadingsDetails({ data = {} }) {
    const counts = data.counts ?? {};
    const headings = Array.isArray(data.headings) ? data.headings : [];

    return (
        <div className="details">
            <h3 className="subheading">Encabezados por nivel</h3>
            <ul className="stat-chips">
                {LEVELS.map((level) => (
                    <li key={level} className="stat-chip">
                        <span className="stat-chip__label">{level.toUpperCase()}</span>
                        <span className="stat-chip__value">{counts[level] ?? 0}</span>
                    </li>
                ))}
            </ul>

            {headings.length > 0 && (
                <>
                    <h3 className="subheading">Estructura del documento</h3>
                    <ol className="outline">
                        {headings.map((heading, index) => (
                            <li
                                key={index}
                                className="outline__item"
                                style={{ '--level': Math.max(0, (heading.level ?? 1) - 1) }}
                            >
                                <span className="outline__tag">H{heading.level}</span>
                                <span className={heading.text ? '' : 'muted'}>
                                    {heading.text || '(vacío)'}
                                </span>
                            </li>
                        ))}
                    </ol>
                    {data.truncated && (
                        <p className="muted">
                            Se muestran los primeros {headings.length} encabezados.
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

export default HeadingsDetails;
