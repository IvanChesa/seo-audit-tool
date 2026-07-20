import { useState } from 'react';
import { createAudit } from '../api/audits';

function AuditForm({ onAuditCreated }) {
    const [url, setUrl] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const audit = await createAudit(url);
            onAuditCreated(audit);
            setUrl('');
        } catch {
            setError('No se pudo crear la auditoría. Revisa que la URL sea válida.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="audit-form">
            <div className="audit-form-row">
                <input
                    type="url"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    placeholder="https://ejemplo.com"
                    required
                    className="audit-form-input"
                />
                <button type="submit" disabled={loading} className="audit-form-button">
                    {loading ? 'Analizando...' : 'Analizar'}
                </button>
            </div>
            {error && <p className="form-error">{error}</p>}
        </form>
    );
}

export default AuditForm;
