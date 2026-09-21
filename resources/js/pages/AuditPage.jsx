import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { createAudit, deleteAudit } from '../api/audits';
import { describeApiError } from '../api/errors';
import AnalysisProgress from '../components/AnalysisProgress';
import AuditReport from '../components/report/AuditReport';
import ScoreGauge from '../components/ScoreGauge';
import Alert from '../components/ui/Alert';
import Badge from '../components/ui/Badge';
import ConfirmDialog from '../components/ui/ConfirmDialog';
import Icon from '../components/ui/Icon';
import { useAudit } from '../hooks/useAudit';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { formatDateTime } from '../lib/format';
import { AUDIT_STATUS, isFinished, lookup } from '../lib/labels';
import { hostnameOf } from '../lib/url';

function NotFound() {
    return (
        <div className="page-message">
            <h1>Auditoría no encontrada</h1>
            <p>Puede que se haya eliminado o que el enlace no sea correcto.</p>
            <p>
                <Link to="/history">Ver el historial</Link> ·{' '}
                <Link to="/">Crear una auditoría</Link>
            </p>
        </div>
    );
}

function AuditView({ id }) {
    const navigate = useNavigate();
    const { status, audit, error, reload } = useAudit(id);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [busyAction, setBusyAction] = useState(null);
    const [actionError, setActionError] = useState(null);
    const [copied, setCopied] = useState(false);

    useDocumentTitle(audit ? `Auditoría de ${hostnameOf(audit.url)}` : 'Auditoría');

    if (status === 'loading') {
        return (
            <p className="loading" role="status">
                <Icon name="spinner" /> Cargando auditoría…
            </p>
        );
    }

    if (!audit) {
        if (error?.kind === 'not_found') return <NotFound />;

        return (
            <Alert
                type="error"
                title="No se pudo cargar la auditoría"
                action={
                    <button type="button" className="button" onClick={reload}>
                        Reintentar
                    </button>
                }
            >
                <p>{error?.message}</p>
            </Alert>
        );
    }

    const statusInfo = lookup(AUDIT_STATUS, audit.status);
    const host = hostnameOf(audit.url);

    async function repeatAudit() {
        setBusyAction('repeat');
        setActionError(null);

        try {
            const created = await createAudit(audit.url);
            navigate(`/audits/${created.id}`);
        } catch (requestError) {
            setActionError(describeApiError(requestError).message);
            setBusyAction(null);
        }
    }

    async function removeAudit() {
        setBusyAction('delete');
        setActionError(null);

        try {
            await deleteAudit(audit.id);
            navigate('/history', { state: { flash: `Se ha eliminado la auditoría de ${host}.` } });
        } catch (requestError) {
            setConfirmOpen(false);
            setActionError(describeApiError(requestError).message);
            setBusyAction(null);
        }
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            setCopied(true);
        } catch {
            setActionError(
                'No se pudo copiar el enlace. Copia la dirección desde la barra del navegador.',
            );
        }
    }

    return (
        <article className="audit-page" aria-labelledby="audit-title">
            <header className="page-header">
                <p className="eyebrow">Auditoría #{audit.id}</p>
                <h1 id="audit-title" className="page-title break">
                    {host}
                </h1>
                <p className="mono break">
                    <a href={audit.url} target="_blank" rel="noopener noreferrer nofollow">
                        {audit.url}
                        <span className="visually-hidden"> (se abre en una pestaña nueva)</span>
                    </a>
                </p>
                <div className="page-header__meta">
                    <Badge
                        tone={statusInfo.tone}
                        icon={statusInfo.icon}
                        label={statusInfo.label}
                        srPrefix="Estado:"
                    />
                    <span className="muted">Creada el {formatDateTime(audit.created_at)}</span>
                </div>
                <div className="toolbar">
                    <button
                        type="button"
                        className="button button--secondary"
                        onClick={repeatAudit}
                        disabled={busyAction !== null}
                    >
                        <Icon name="refresh" />{' '}
                        {busyAction === 'repeat' ? 'Creando…' : 'Repetir auditoría'}
                    </button>
                    <button type="button" className="button button--secondary" onClick={copyLink}>
                        <Icon name="link" /> Copiar enlace
                    </button>
                    <button
                        type="button"
                        className="button button--danger-outline"
                        onClick={() => setConfirmOpen(true)}
                        disabled={busyAction !== null}
                    >
                        <Icon name="trash" /> Eliminar
                    </button>
                </div>
                {/* Always rendered so screen readers pick up the change. */}
                <p className="muted" role="status">
                    {copied ? 'Enlace copiado al portapapeles.' : ''}
                </p>
            </header>

            {actionError && <Alert type="error">{actionError}</Alert>}

            {status === 'error' && (
                <Alert
                    type="warning"
                    title="No se pudo actualizar el estado de la auditoría"
                    action={
                        <button type="button" className="button" onClick={reload}>
                            Reintentar
                        </button>
                    }
                >
                    <p>{error?.message}</p>
                </Alert>
            )}

            {audit.legacy && (
                <Alert type="info" title="Informe de una versión anterior">
                    <p>
                        Esta auditoría se creó con una versión anterior de la herramienta y su
                        informe no es compatible con el formato actual. Repite la auditoría para
                        obtener el informe completo.
                    </p>
                </Alert>
            )}

            {!isFinished(audit.status) && !audit.legacy && (
                <AnalysisProgress progress={audit.progress} status={audit.status} />
            )}

            {audit.status === 'failed' && (
                <Alert type="error" title="No se pudo completar la auditoría">
                    <p>{audit.error?.message ?? 'Se produjo un error durante el análisis.'}</p>
                    <p>Comprueba que la URL sea pública y accesible, y vuelve a intentarlo.</p>
                </Alert>
            )}

            {audit.status === 'completed' &&
                (audit.legacy ? (
                    <div className="card">
                        <ScoreGauge score={audit.score} rating={audit.score_rating} />
                    </div>
                ) : (
                    <AuditReport audit={audit} />
                ))}

            <ConfirmDialog
                open={confirmOpen}
                title="¿Eliminar esta auditoría?"
                confirmLabel="Eliminar"
                destructive
                busy={busyAction === 'delete'}
                onConfirm={removeAudit}
                onCancel={() => setConfirmOpen(false)}
            >
                <p>
                    Se borrará la auditoría de <strong>{host}</strong> con todos sus resultados.
                    Esta acción no se puede deshacer.
                </p>
            </ConfirmDialog>
        </article>
    );
}

function AuditPage() {
    const { id } = useParams();

    if (!/^\d+$/.test(id ?? '')) {
        return <NotFound />;
    }

    // The key restarts polling and local state when navigating between audits.
    return <AuditView key={id} id={id} />;
}

export default AuditPage;
