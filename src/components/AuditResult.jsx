import { useState, useEffect, useRef } from 'react';
import { getAudit } from '../api/audits';

function AuditResult({ auditId }) {
    const [audit, setAudit] = useState(null);
    const intervalRef = useRef(null);

    useEffect(() => {
        const fetchAudit = async () => {
            try {
                const data = await getAudit(auditId);
                setAudit(data);

                // Cuando termina (completed o failed), dejamos de preguntar
                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(intervalRef.current);
                }
            } catch (err) {
                console.error('Error fetching audit:', err);
                clearInterval(intervalRef.current);
            }
        };

        fetchAudit(); // primera consulta inmediata
        intervalRef.current = setInterval(fetchAudit, 2000); // luego cada 2s

        // Limpieza: si el componente se desmonta, paramos el polling
        return () => clearInterval(intervalRef.current);
    }, [auditId]);

    if (!audit) {
        return <p>Cargando...</p>;
    }

    const metaResult = audit.results.find((r) => r.type === 'meta');
    const fetchResult = audit.results.find((r) => r.type === 'fetch');

    return (
        <div>
            <h2>{audit.url}</h2>
            <p>Estado: {audit.status}</p>

            {(audit.status === 'pending' || audit.status === 'processing') && (
                <p>Analizando la página, esto puede tardar unos segundos...</p>
            )}

            {fetchResult && (
                <div>
                    <h3>Descarga de la página</h3>
                    <p>Código HTTP: {fetchResult.data.status_code}</p>
                    <p>Tamaño: {fetchResult.data.content_length} bytes</p>
                </div>
            )}

            {metaResult && (
                <div>
                    <h3>Meta etiquetas (puntuación: {metaResult.score}/100)</h3>
                    <p>Título: {metaResult.data.title || 'No encontrado'} ({metaResult.data.title_length} caracteres)</p>
                    <p>Meta description: {metaResult.data.meta_description || 'No encontrada'}</p>
                    <p>Canonical: {metaResult.data.canonical || 'No encontrado'}</p>

                    {metaResult.data.issues.length > 0 && (
                        <div>
                            <h4>Problemas detectados:</h4>
                            <ul>
                                {metaResult.data.issues.map((issue) => (
                                    <li key={issue}>{issue}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default AuditResult;