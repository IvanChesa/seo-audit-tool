import { lazy, Suspense } from 'react';
import { formatNumber } from '../../../lib/format';

const KeywordChart = lazy(() => import('./KeywordChart'));

function ContentDetails({ data = {} }) {
    const terms = Array.isArray(data.top_terms) ? data.top_terms : [];
    const images = data.images ?? {};
    const missingExamples = Array.isArray(images.missing_alt_examples)
        ? images.missing_alt_examples
        : [];

    return (
        <div className="details">
            <h3 className="subheading">Términos más frecuentes</h3>
            {terms.length === 0 ? (
                <p className="empty-note">No hay suficiente texto para calcular frecuencias.</p>
            ) : (
                <div className="split">
                    <div className="table-wrapper">
                        <table className="table">
                            <caption className="visually-hidden">
                                Términos más frecuentes del contenido
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Término</th>
                                    <th scope="col">Apariciones</th>
                                    <th scope="col">Densidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                {terms.map((term) => (
                                    <tr key={term.term}>
                                        <th scope="row">{term.term}</th>
                                        <td>{formatNumber(term.count)}</td>
                                        <td>
                                            {formatNumber(term.density, {
                                                maximumFractionDigits: 2,
                                            })}{' '}
                                            %
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Suspense fallback={<p className="muted">Cargando gráfica…</p>}>
                        <KeywordChart terms={terms} />
                    </Suspense>
                </div>
            )}
            <p className="muted">
                La densidad es el porcentaje de palabras del texto que corresponden a cada término.
                Es orientativa: los buscadores no premian una densidad concreta.
            </p>

            <h3 className="subheading">Imágenes</h3>
            <ul className="stat-chips">
                <li className="stat-chip">
                    <span className="stat-chip__label">Total</span>
                    <span className="stat-chip__value">{images.total ?? 0}</span>
                </li>
                <li className="stat-chip">
                    <span className="stat-chip__label">Sin alt</span>
                    <span className="stat-chip__value">{images.missing_alt ?? 0}</span>
                </li>
                <li className="stat-chip">
                    <span className="stat-chip__label">Decorativas (alt vacío)</span>
                    <span className="stat-chip__value">{images.decorative ?? 0}</span>
                </li>
            </ul>
            {missingExamples.length > 0 && (
                <>
                    <p>Imágenes sin atributo alt:</p>
                    <ul className="plain-list mono break">
                        {missingExamples.map((src, index) => (
                            <li key={`${src}-${index}`}>{src}</li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
}

export default ContentDetails;
