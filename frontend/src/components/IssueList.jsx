// Traducciones legibles de los códigos de issue que genera el backend.
const ISSUE_LABELS = {
    missing_title: 'Falta la etiqueta <title>',
    title_too_long: 'El título supera los 60 caracteres',
    title_too_short: 'El título tiene menos de 30 caracteres',
    missing_meta_description: 'Falta la meta description',
    meta_description_too_long: 'La meta description supera los 160 caracteres',
    missing_canonical: 'Falta la etiqueta canonical',
    missing_h1: 'La página no tiene ningún H1',
    multiple_h1: 'Hay más de un H1 en la página',
    skipped_heading_level: 'Hay saltos en la jerarquía de encabezados (ej. H1 → H3)',
    keyword_stuffing: 'Alguna palabra supera el 5% de densidad (keyword stuffing)',
    broken_links_found: 'Se encontraron enlaces rotos',
};

function IssueList({ issues }) {
    if (!issues || issues.length === 0) {
        return <p className="section-ok">Sin problemas detectados.</p>;
    }

    return (
        <ul className="issue-list">
            {issues.map((issue) => (
                <li key={issue}>{ISSUE_LABELS[issue] ?? issue}</li>
            ))}
        </ul>
    );
}

export default IssueList;
