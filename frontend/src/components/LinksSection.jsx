function LinksSection({ result, brokenLinks }) {
    const { total_links: totalLinks, broken_count: brokenCount, error } = result.data;

    if (error) {
        return <p className="section-error">No se pudieron comprobar los enlaces ({error}).</p>;
    }

    return (
        <div>
            <p className="section-note">
                {totalLinks} enlaces comprobados, {brokenCount} rotos.
            </p>

            {brokenLinks.length > 0 ? (
                <div className="table-wrapper">
                    <table className="broken-links-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Código</th>
                                <th>Texto del enlace</th>
                            </tr>
                        </thead>
                        <tbody>
                            {brokenLinks.map((link) => (
                                <tr key={link.id}>
                                    <td className="link-url">
                                        <a href={link.url} target="_blank" rel="noopener noreferrer">
                                            {link.url}
                                        </a>
                                    </td>
                                    <td>
                                        <span className="status-badge bad">
                                            {link.status_code ?? 'Sin respuesta'}
                                        </span>
                                    </td>
                                    <td>{link.link_text || <em>(sin texto)</em>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="section-ok">No se encontraron enlaces rotos.</p>
            )}
        </div>
    );
}

export default LinksSection;
