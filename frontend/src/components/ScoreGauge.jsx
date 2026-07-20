// Círculo de progreso SVG para el score global (0-100).
// Verde >= 80, naranja 50-79, rojo < 50.
function scoreColor(score) {
    if (score >= 80) return 'var(--good)';
    if (score >= 50) return 'var(--warn)';
    return 'var(--bad)';
}

function ScoreGauge({ score }) {
    const radius = 70;
    const stroke = 12;
    const circumference = 2 * Math.PI * radius;
    const value = score ?? 0;
    // Parte del círculo que se pinta según el score.
    const offset = circumference * (1 - value / 100);
    const color = scoreColor(value);

    return (
        <div className="score-gauge" role="img" aria-label={`Puntuación global: ${value} de 100`}>
            <svg width="170" height="170" viewBox="0 0 170 170">
                <circle
                    cx="85" cy="85" r={radius}
                    fill="none"
                    stroke="var(--surface-2)"
                    strokeWidth={stroke}
                />
                <circle
                    cx="85" cy="85" r={radius}
                    fill="none"
                    stroke={color}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    transform="rotate(-90 85 85)"
                    style={{ transition: 'stroke-dashoffset 0.8s ease' }}
                />
                <text
                    x="85" y="80"
                    textAnchor="middle"
                    className="score-gauge-value"
                    fill={color}
                >
                    {value}
                </text>
                <text x="85" y="104" textAnchor="middle" className="score-gauge-label">
                    / 100
                </text>
            </svg>
            <p className="score-gauge-title">Puntuación SEO global</p>
        </div>
    );
}

export default ScoreGauge;
