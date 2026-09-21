const OPEN_GRAPH_PROPERTIES = ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'];

function Missing() {
    return <em className="muted">No encontrado</em>;
}

function MetaDetails({ data = {} }) {
    const openGraph = data.open_graph ?? {};
    const robots = Array.isArray(data.robots) ? data.robots : [];

    return (
        <div className="details">
            <h3 className="subheading">Vista previa aproximada en buscadores</h3>
            <div className="serp-preview">
                <p className="serp-preview__title">{data.title || 'Sin título'}</p>
                <p className="serp-preview__url">{data.canonical || 'URL de la página'}</p>
                <p className="serp-preview__description">
                    {data.meta_description ||
                        'Sin meta description: el buscador elegirá un fragmento del texto.'}
                </p>
            </div>

            <dl className="definition-list">
                <dt>Título ({data.title_length ?? 0} caracteres)</dt>
                <dd>{data.title || <Missing />}</dd>
                <dt>Meta description ({data.meta_description_length ?? 0} caracteres)</dt>
                <dd>{data.meta_description || <Missing />}</dd>
                <dt>URL canónica</dt>
                <dd className="mono break">{data.canonical || <Missing />}</dd>
                <dt>Directivas robots</dt>
                <dd>
                    {robots.length > 0 ? robots.join(', ') : 'Ninguna (se aplica index, follow)'}
                </dd>
            </dl>

            <h3 className="subheading">Open Graph</h3>
            <div className="table-wrapper">
                <table className="table">
                    <caption className="visually-hidden">Etiquetas Open Graph</caption>
                    <thead>
                        <tr>
                            <th scope="col">Propiedad</th>
                            <th scope="col">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        {OPEN_GRAPH_PROPERTIES.map((property) => (
                            <tr key={property}>
                                <th scope="row" className="mono">
                                    {property}
                                </th>
                                <td className="break">{openGraph[property] || <Missing />}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default MetaDetails;
