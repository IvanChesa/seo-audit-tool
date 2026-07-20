// Umbrales oficiales de Google para Core Web Vitals:
// verde (good), naranja (needs improvement), rojo (poor).
const THRESHOLDS = {
    lcp: { good: 2500, poor: 4000, unit: 'ms', label: 'LCP (Largest Contentful Paint)' },
    cls: { good: 0.1, poor: 0.25, unit: '', label: 'CLS (Cumulative Layout Shift)' },
    fcp: { good: 1800, poor: 3000, unit: 'ms', label: 'FCP (First Contentful Paint)' },
};

function metricStatus(key, value) {
    const t = THRESHOLDS[key];
    if (value === null || value === undefined) return 'unknown';
    if (value <= t.good) return 'good';
    if (value <= t.poor) return 'warn';
    return 'bad';
}

function SpeedSection({ result }) {
    const { performance_score: performanceScore, error } = result.data;

    if (error === 'api_key_missing') {
        return (
            <p className="section-warning">
                Falta configurar la API key de PageSpeed Insights
                (variable <code>PAGESPEED_API_KEY</code> en el .env del backend).
            </p>
        );
    }

    if (error) {
        return <p className="section-error">No se pudo obtener PageSpeed ({error}).</p>;
    }

    return (
        <div>
            {performanceScore !== null && (
                <p className="section-note">
                    Rendimiento según Lighthouse (móvil): <strong>{performanceScore}/100</strong>
                </p>
            )}

            <div className="vitals-grid">
                {Object.entries(THRESHOLDS).map(([key, config]) => {
                    const metric = result.data[key];
                    const status = metricStatus(key, metric?.value);

                    return (
                        <div key={key} className={`vital-card ${status}`}>
                            <span className="vital-label">{config.label}</span>
                            <span className="vital-value">
                                {metric?.display ?? 'N/D'}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default SpeedSection;
