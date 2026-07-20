const WEIGHTS = [
    { type: 'meta', label: 'Meta', weight: 25 },
    { type: 'headings', label: 'Encabezados', weight: 20 },
    { type: 'keywords', label: 'Keywords', weight: 15 },
    { type: 'links', label: 'Enlaces', weight: 25 },
    { type: 'speed', label: 'Velocidad', weight: 15 },
];

function ScoreBreakdown({ results }) {
    const byType = Object.fromEntries(
        (results ?? []).filter((r) => r.score !== null && r.score !== undefined)
            .map((r) => [r.type, r.score])
    );

    return (
        <div className="score-breakdown">
            <p className="section-note">Desglose (media ponderada):</p>
            <ul>
                {WEIGHTS.map(({ type, label, weight }) => {
                    const score = byType[type];
                    const missing = score === undefined;

                    return (
                        <li key={type}>
                            <span className="breakdown-label">
                                {label} <span className="breakdown-weight">({weight}%)</span>
                            </span>
                            <span className={`breakdown-score ${missing ? 'missing' : ''}`}>
                                {missing ? 'N/D' : `${score}/100`}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default ScoreBreakdown;
