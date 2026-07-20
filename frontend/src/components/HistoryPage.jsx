import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { listAudits } from '../api/audits';

function scoreClass(score) {
    if (score === null || score === undefined) return '';
    if (score >= 80) return 'good';
    if (score >= 50) return 'warn';
    return 'bad';
}

function HistoryPage() {
    const [audits, setAudits] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        listAudits()
            .then((data) => setAudits(data.data ?? [])) // la API pagina: los items van en .data
            .catch(() => setError('No se pudo cargar el historial.'));
    }, []);

    if (error) return <p className="section-error">{error}</p>;
    if (!audits) return <p className="loading">Cargando...</p>;

    return (
        <div className="history">
            <h2>Historial de auditorías</h2>

            {audits.length === 0 ? (
                <p className="section-note">
                    Todavía no hay auditorías. <Link to="/">Crea la primera</Link>.
                </p>
            ) : (
                <ul className="history-list">
                    {audits.map((audit) => (
                        <li key={audit.id}>
                            <Link to={`/audits/${audit.id}`} className="history-item">
                                <div className="history-info">
                                    <span className="history-url">{audit.url}</span>
                                    <span className="history-date">
                                        {new Date(audit.created_at).toLocaleString()}
                                    </span>
                                </div>
                                <div className="history-meta">
                                    <span className={`status-badge status-${audit.status}`}>
                                        {audit.status}
                                    </span>
                                    {audit.score !== null && (
                                        <span className={`score-pill ${scoreClass(audit.score)}`}>
                                            {audit.score}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default HistoryPage;
