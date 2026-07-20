const STEPS = [
    { type: 'fetch', label: 'Descarga de la página' },
    { type: 'meta', label: 'Meta etiquetas' },
    { type: 'headings', label: 'Encabezados' },
    { type: 'keywords', label: 'Palabras clave' },
    { type: 'links', label: 'Enlaces' },
    { type: 'speed', label: 'Velocidad' },
];

/**
 * Checklist visual de qué análisis ya terminaron mientras el batch
 * sigue procesando. Así el usuario ve progreso real, no solo un spinner.
 */
function AnalysisProgress({ results }) {
    const doneTypes = new Set((results ?? []).map((r) => r.type));
    const completed = STEPS.filter((s) => doneTypes.has(s.type)).length;

    return (
        <div className="analysis-progress">
            <div className="analysis-progress-header">
                <span className="spinner" aria-hidden="true" />
                <span>
                    Analizando… {completed}/{STEPS.length} pasos
                </span>
            </div>
            <ul className="analysis-progress-list">
                {STEPS.map((step) => {
                    const done = doneTypes.has(step.type);
                    return (
                        <li key={step.type} className={done ? 'done' : 'pending'}>
                            <span className="step-icon" aria-hidden="true">
                                {done ? '✓' : '·'}
                            </span>
                            {step.label}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default AnalysisProgress;
