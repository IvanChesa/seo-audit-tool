import { formatNumber } from '../../lib/format';

/**
 * How the global score is built: every section with its nominal weight, the
 * weight it actually had and its score. Sections that were not run are listed
 * as excluded so the user understands why the weights change.
 */
function ScoreBreakdown({ breakdown }) {
    if (!breakdown?.sections) return null;

    return (
        <section className="card" aria-labelledby="breakdown-title">
            <h2 id="breakdown-title" className="card__title">
                Cómo se calcula la puntuación
            </h2>
            <p className="card__intro">
                Media ponderada de las secciones. Las que no se pudieron ejecutar no cuentan y su
                peso se reparte entre las demás.
            </p>

            <div className="table-wrapper">
                <table className="table">
                    <caption className="visually-hidden">
                        Desglose de la puntuación por sección
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Sección</th>
                            <th scope="col">Peso</th>
                            <th scope="col">Peso aplicado</th>
                            <th scope="col">Puntuación</th>
                        </tr>
                    </thead>
                    <tbody>
                        {breakdown.sections.map((section) => (
                            <tr key={section.key}>
                                <th scope="row">{section.label}</th>
                                <td className="nowrap">{section.weight} %</td>
                                <td>
                                    {section.counted
                                        ? `${formatNumber(section.effective_weight, { maximumFractionDigits: 1 })} %`
                                        : 'No cuenta'}
                                </td>
                                <td>
                                    {section.counted ? (
                                        <span className="meter">
                                            <span className="meter__bar" aria-hidden="true">
                                                <span style={{ width: `${section.score}%` }} />
                                            </span>
                                            {section.score}/100
                                        </span>
                                    ) : (
                                        'Sin puntuación'
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {breakdown.critical_cap_applied && (
                <p className="note note--warn">
                    Hay al menos un problema crítico (la página no es indexable), así que la
                    puntuación global se limita a {breakdown.critical_cap}. Sin ese límite sería{' '}
                    {breakdown.weighted_average}.
                </p>
            )}
        </section>
    );
}

export default ScoreBreakdown;
