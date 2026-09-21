import { formatNumber } from '../../../lib/format';

const ERROR_LABELS = {
    timeout: 'Sin respuesta (tiempo agotado)',
    connection_failed: 'No se pudo conectar',
    dns_error: 'Dominio no encontrado',
    tls_error: 'Error de certificado',
    too_many_redirects: 'Demasiadas redirecciones',
};

function LinksDetails({ data = {} }) {
    const totals = data.totals ?? {};
    const checked = data.checked ?? {};
    const broken = Array.isArray(data.broken_links) ? data.broken_links : [];

    return (
        <div className="details">
            <ul className="stat-chips">
                <li className="stat-chip">
                    <span className="stat-chip__label">Internos</span>
                    <span className="stat-chip__value">{formatNumber(totals.internal ?? 0)}</span>
                </li>
                <li className="stat-chip">
                    <span className="stat-chip__label">Externos</span>
                    <span className="stat-chip__value">{formatNumber(totals.external ?? 0)}</span>
                </li>
                <li className="stat-chip">
                    <span className="stat-chip__label">nofollow</span>
                    <span className="stat-chip__value">{formatNumber(totals.nofollow ?? 0)}</span>
                </li>
                <li className="stat-chip">
                    <span className="stat-chip__label">Rotos</span>
                    <span className="stat-chip__value">{formatNumber(broken.length)}</span>
                </li>
            </ul>

            <p className="muted">
                Se comprobaron {checked.checked ?? 0} de {totals.unique ?? 0} URL distintas (límite
                por auditoría: {checked.limit ?? '—'}).
                {checked.restricted > 0 &&
                    ` ${checked.restricted} no se pudieron verificar porque el servidor rechazó la comprobación automática (401, 403 o 429).`}
                {checked.skipped > 0 &&
                    ` ${checked.skipped} no se comprobaron por apuntar a direcciones no públicas.`}
            </p>

            {broken.length > 0 && (
                <>
                    <h3 className="subheading">Enlaces rotos</h3>
                    <div className="table-wrapper">
                        <table className="table">
                            <caption className="visually-hidden">Enlaces rotos encontrados</caption>
                            <thead>
                                <tr>
                                    <th scope="col">URL</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Respuesta</th>
                                    <th scope="col">Texto del enlace</th>
                                </tr>
                            </thead>
                            <tbody>
                                {broken.map((link) => (
                                    <tr key={link.url}>
                                        <td className="break">
                                            <a
                                                href={link.url}
                                                target="_blank"
                                                rel="noopener noreferrer nofollow"
                                            >
                                                {link.url}
                                                <span className="visually-hidden">
                                                    {' '}
                                                    (se abre en una pestaña nueva)
                                                </span>
                                            </a>
                                        </td>
                                        <td>{link.is_internal ? 'Interno' : 'Externo'}</td>
                                        <td>
                                            {link.status_code
                                                ? `HTTP ${link.status_code}`
                                                : (ERROR_LABELS[link.error] ?? 'Sin respuesta')}
                                        </td>
                                        <td>
                                            {link.link_text || (
                                                <em className="muted">(sin texto)</em>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
}

export default LinksDetails;
