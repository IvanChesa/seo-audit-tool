import { useState, useEffect, useRef, lazy, Suspense } from 'react';
import { getAudit } from '../api/audits';
import ScoreGauge from './ScoreGauge';
import IssueList from './IssueList';
import HeadingsSection from './HeadingsSection';
import LinksSection from './LinksSection';
import SpeedSection from './SpeedSection';
import AnalysisProgress from './AnalysisProgress';
import ScoreBreakdown from './ScoreBreakdown';

// Recharts pesa mucho: lo cargamos solo cuando hay datos de keywords.
const KeywordsSection = lazy(() => import('./KeywordsSection'));

function Section({ title, score, children }) {
    return (
        <section className="result-card">
            <div className="result-card-header">
                <h3>{title}</h3>
                {score !== null && score !== undefined && (
                    <span className={`score-pill ${score >= 80 ? 'good' : score >= 50 ? 'warn' : 'bad'}`}>
                        {score}/100
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}

function AuditResult({ auditId }) {
    const [audit, setAudit] = useState(null);
    const [error, setError] = useState(null);
    const intervalRef = useRef(null);

    useEffect(() => {
        const fetchAudit = async () => {
            try {
                const data = await getAudit(auditId);
                setAudit(data);

                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(intervalRef.current);
                }
            } catch {
                setError('No se pudo cargar la auditoría.');
                clearInterval(intervalRef.current);
            }
        };

        fetchAudit();
        intervalRef.current = setInterval(fetchAudit, 2000);

        return () => clearInterval(intervalRef.current);
    }, [auditId]);

    if (error) {
        return <p className="section-error">{error}</p>;
    }

    if (!audit) {
        return <p className="loading">Cargando...</p>;
    }

    const findResult = (type) => audit.results.find((r) => r.type === type);

    const fetchResult = findResult('fetch');
    const metaResult = findResult('meta');
    const headingsResult = findResult('headings');
    const keywordsResult = findResult('keywords');
    const linksResult = findResult('links');
    const speedResult = findResult('speed');

    const inProgress = audit.status === 'pending' || audit.status === 'processing';

    return (
        <div className="audit-result">
            <header className="result-header">
                <h2 className="result-url">{audit.url}</h2>
                <span className={`status-badge status-${audit.status}`}>{audit.status}</span>
            </header>

            {inProgress && <AnalysisProgress results={audit.results} />}

            {audit.status === 'failed' && (
                <p className="section-error">
                    La auditoría falló. Comprueba que la URL sea accesible.
                </p>
            )}

            {audit.status === 'completed' && (
                <>
                    <ScoreGauge score={audit.score} />
                    <ScoreBreakdown results={audit.results} />
                </>
            )}

            {fetchResult && (
                <Section title="Descarga de la página">
                    <p className="section-note">
                        {fetchResult.data.error
                            ? `Error: ${fetchResult.data.error}`
                            : `Código HTTP ${fetchResult.data.status_code} · ${(fetchResult.data.content_length / 1024).toFixed(1)} KB`}
                    </p>
                </Section>
            )}

            {metaResult && (
                <Section title="Meta etiquetas" score={metaResult.score}>
                    {metaResult.data.error ? (
                        <p className="section-error">No se pudo analizar meta ({metaResult.data.error}).</p>
                    ) : (
                        <>
                            <dl className="meta-list">
                                <dt>Título ({metaResult.data.title_length} caracteres)</dt>
                                <dd>{metaResult.data.title || 'No encontrado'}</dd>
                                <dt>Meta description ({metaResult.data.meta_description_length} caracteres)</dt>
                                <dd>{metaResult.data.meta_description || 'No encontrada'}</dd>
                                <dt>Canonical</dt>
                                <dd>{metaResult.data.canonical || 'No encontrado'}</dd>
                                <dt>Robots</dt>
                                <dd>{metaResult.data.robots || 'No especificado'}</dd>
                            </dl>
                            <IssueList issues={metaResult.data.issues ?? []} />
                        </>
                    )}
                </Section>
            )}

            {headingsResult && (
                <Section title="Encabezados" score={headingsResult.score}>
                    <HeadingsSection result={headingsResult} />
                </Section>
            )}

            {keywordsResult && (
                <Section title="Palabras clave" score={keywordsResult.score}>
                    <Suspense fallback={<p className="loading">Cargando gráfica...</p>}>
                        <KeywordsSection result={keywordsResult} />
                    </Suspense>
                </Section>
            )}

            {linksResult && (
                <Section title="Enlaces" score={linksResult.score}>
                    <LinksSection result={linksResult} brokenLinks={audit.broken_links ?? []} />
                </Section>
            )}

            {speedResult && (
                <Section title="Velocidad (PageSpeed)" score={speedResult.score}>
                    <SpeedSection result={speedResult} />
                </Section>
            )}
        </div>
    );
}

export default AuditResult;
