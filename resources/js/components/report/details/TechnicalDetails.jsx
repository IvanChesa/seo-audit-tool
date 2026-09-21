function TechnicalDetails({ data = {} }) {
    const redirects = Array.isArray(data.redirects) ? data.redirects : [];
    const robots = data.robots_txt ?? {};
    const sitemaps = Array.isArray(robots.sitemaps) ? robots.sitemaps : [];

    return (
        <div className="details">
            {redirects.length > 0 && (
                <>
                    <h3 className="subheading">Cadena de redirecciones</h3>
                    <ol className="plain-list mono">
                        {redirects.map((hop, index) => (
                            <li key={`${hop.url}-${index}`}>
                                {hop.url} <span className="muted">({hop.status})</span>
                            </li>
                        ))}
                        <li>
                            {data.final_url} <span className="muted">(final)</span>
                        </li>
                    </ol>
                </>
            )}

            {robots.url && (
                <>
                    <h3 className="subheading">robots.txt</h3>
                    <p className="mono break">{robots.url}</p>
                    {sitemaps.length > 0 && (
                        <p>
                            Sitemaps declarados:{' '}
                            <span className="mono break">{sitemaps.join(', ')}</span>
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

export default TechnicalDetails;
