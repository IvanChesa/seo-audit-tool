import { useEffect, useId, useState } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { createAudit, deleteAudit, listAudits } from '../api/audits';
import { describeApiError } from '../api/errors';
import Alert from '../components/ui/Alert';
import Badge from '../components/ui/Badge';
import ConfirmDialog from '../components/ui/ConfirmDialog';
import Icon from '../components/ui/Icon';
import Pagination from '../components/ui/Pagination';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { formatDateTime } from '../lib/format';
import { AUDIT_STATUS, AUDIT_STATUS_OPTIONS, RATING, lookup } from '../lib/labels';
import { hostnameOf } from '../lib/url';

const PER_PAGE = 10;

function readFilters(searchParams) {
    const page = Number.parseInt(searchParams.get('page') ?? '1', 10);
    const status = searchParams.get('status') ?? '';

    return {
        page: Number.isInteger(page) && page > 0 ? page : 1,
        status: AUDIT_STATUS[status] ? status : '',
        search: searchParams.get('search') ?? '',
    };
}

/**
 * Paginated history. Page and filters live in the URL, so every view of the
 * history can be bookmarked or shared.
 */
function HistoryPage() {
    useDocumentTitle('Historial');

    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams, setSearchParams] = useSearchParams();
    const { page, status, search } = readFilters(searchParams);

    const [searchDraft, setSearchDraft] = useState(search);
    const [result, setResult] = useState({ status: 'loading', items: [], meta: null, error: null });
    const [reloadKey, setReloadKey] = useState(0);
    const [notice, setNotice] = useState(location.state?.flash ?? null);
    const [actionError, setActionError] = useState(null);
    const [pendingDelete, setPendingDelete] = useState(null);
    const [busyId, setBusyId] = useState(null);
    const searchId = useId();
    const statusId = useId();

    // Keep the search box in sync when the URL changes (back/forward buttons).
    useEffect(() => {
        setSearchDraft(search);
    }, [search]);

    useEffect(() => {
        const controller = new AbortController();
        setResult((current) => ({ ...current, status: 'loading', error: null }));

        listAudits({ page, perPage: PER_PAGE, status, search }, { signal: controller.signal })
            .then(({ items, meta }) => setResult({ status: 'success', items, meta, error: null }))
            .catch((error) => {
                const info = describeApiError(error);
                if (info.kind !== 'cancelled') {
                    setResult({ status: 'error', items: [], meta: null, error: info });
                }
            });

        return () => controller.abort();
    }, [page, status, search, reloadKey]);

    function updateParams(changes) {
        const next = new URLSearchParams(searchParams);

        Object.entries(changes).forEach(([key, value]) => {
            if (value === '' || value === null || (key === 'page' && value === 1)) {
                next.delete(key);
            } else {
                next.set(key, String(value));
            }
        });

        setSearchParams(next);
    }

    function clearFilters() {
        setSearchDraft('');
        setSearchParams(new URLSearchParams());
    }

    async function repeatAudit(audit) {
        setBusyId(audit.id);
        setActionError(null);

        try {
            const created = await createAudit(audit.url);
            navigate(`/audits/${created.id}`);
        } catch (error) {
            setActionError(describeApiError(error).message);
            setBusyId(null);
        }
    }

    async function confirmDelete() {
        const audit = pendingDelete;
        setBusyId(audit.id);
        setActionError(null);

        try {
            await deleteAudit(audit.id);
            setNotice(`Se ha eliminado la auditoría de ${hostnameOf(audit.url)}.`);

            // If the page is now empty, go back one page; otherwise reload it.
            if (result.items.length === 1 && page > 1) {
                updateParams({ page: page - 1 });
            } else {
                setReloadKey((key) => key + 1);
            }
        } catch (error) {
            setActionError(describeApiError(error).message);
        } finally {
            setPendingDelete(null);
            setBusyId(null);
        }
    }

    const hasFilters = status !== '' || search !== '';
    const { items, meta } = result;

    return (
        <div className="history">
            <header className="page-header">
                <h1 className="page-title">Historial de auditorías</h1>
                <p className="muted">Consulta, repite o elimina las auditorías anteriores.</p>
            </header>

            <p role="status" className={notice ? 'notice' : 'visually-hidden'}>
                {notice ?? ''}
            </p>
            {actionError && <Alert type="error">{actionError}</Alert>}

            <form
                className="filters"
                role="search"
                onSubmit={(event) => {
                    event.preventDefault();
                    updateParams({ search: searchDraft.trim(), page: 1 });
                }}
            >
                <div className="field">
                    <label htmlFor={searchId}>Buscar por URL</label>
                    <input
                        id={searchId}
                        type="search"
                        className="input"
                        value={searchDraft}
                        maxLength={255}
                        placeholder="ejemplo.com"
                        onChange={(event) => setSearchDraft(event.target.value)}
                    />
                </div>
                <div className="field">
                    <label htmlFor={statusId}>Estado</label>
                    <select
                        id={statusId}
                        className="input"
                        value={status}
                        onChange={(event) => updateParams({ status: event.target.value, page: 1 })}
                    >
                        <option value="">Todos</option>
                        {AUDIT_STATUS_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="filters__actions">
                    <button type="submit" className="button button--primary">
                        <Icon name="search" /> Buscar
                    </button>
                    {hasFilters && (
                        <button
                            type="button"
                            className="button button--secondary"
                            onClick={clearFilters}
                        >
                            Quitar filtros
                        </button>
                    )}
                </div>
            </form>

            {result.status === 'loading' && (
                <p className="loading" role="status">
                    <Icon name="spinner" /> Cargando historial…
                </p>
            )}

            {result.status === 'error' && (
                <Alert
                    type="error"
                    title="No se pudo cargar el historial"
                    action={
                        <button
                            type="button"
                            className="button"
                            onClick={() => setReloadKey((key) => key + 1)}
                        >
                            Reintentar
                        </button>
                    }
                >
                    <p>{result.error.message}</p>
                </Alert>
            )}

            {result.status === 'success' && items.length === 0 && (
                <div className="empty-state">
                    {hasFilters ? (
                        <>
                            <p>No hay auditorías que coincidan con los filtros.</p>
                            <button
                                type="button"
                                className="button button--secondary"
                                onClick={clearFilters}
                            >
                                Quitar filtros
                            </button>
                        </>
                    ) : (
                        <p>
                            Todavía no hay auditorías. <Link to="/">Crea la primera</Link>.
                        </p>
                    )}
                </div>
            )}

            {items.length > 0 && (
                <ul className="history-list" aria-busy={result.status === 'loading'}>
                    {items.map((audit) => {
                        const statusInfo = lookup(AUDIT_STATUS, audit.status);
                        const rating = lookup(RATING, audit.score_rating);

                        return (
                            <li key={audit.id} className="history-item">
                                <div className="history-item__main">
                                    <Link
                                        to={`/audits/${audit.id}`}
                                        className="history-item__title"
                                    >
                                        {hostnameOf(audit.url)}
                                    </Link>
                                    <span className="history-item__url mono break">
                                        {audit.url}
                                    </span>
                                    <span className="muted">
                                        {formatDateTime(audit.created_at)}
                                    </span>
                                </div>
                                <div className="history-item__meta">
                                    <Badge
                                        tone={statusInfo.tone}
                                        icon={statusInfo.icon}
                                        label={statusInfo.label}
                                        srPrefix="Estado:"
                                    />
                                    {audit.score !== null && audit.score !== undefined && (
                                        <Badge
                                            tone={rating.tone}
                                            icon={rating.icon}
                                            label={`${audit.score}/100`}
                                            srPrefix="Puntuación:"
                                        />
                                    )}
                                </div>
                                <div className="history-item__actions">
                                    <button
                                        type="button"
                                        className="button button--small button--secondary"
                                        onClick={() => repeatAudit(audit)}
                                        disabled={busyId !== null}
                                        aria-label={`Repetir la auditoría de ${hostnameOf(audit.url)}`}
                                    >
                                        <Icon name="refresh" /> Repetir
                                    </button>
                                    <button
                                        type="button"
                                        className="button button--small button--danger-outline"
                                        onClick={() => setPendingDelete(audit)}
                                        disabled={busyId !== null}
                                        aria-label={`Eliminar la auditoría de ${hostnameOf(audit.url)}`}
                                    >
                                        <Icon name="trash" /> Eliminar
                                    </button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {meta && (
                <Pagination
                    page={meta.current_page}
                    lastPage={meta.last_page}
                    total={meta.total}
                    onChange={(nextPage) => updateParams({ page: nextPage })}
                />
            )}

            <ConfirmDialog
                open={pendingDelete !== null}
                title="¿Eliminar esta auditoría?"
                confirmLabel="Eliminar"
                destructive
                busy={pendingDelete !== null && busyId === pendingDelete.id}
                onConfirm={confirmDelete}
                onCancel={() => setPendingDelete(null)}
            >
                <p>
                    Se borrará la auditoría de{' '}
                    <strong>{pendingDelete ? hostnameOf(pendingDelete.url) : ''}</strong> con todos
                    sus resultados. Esta acción no se puede deshacer.
                </p>
            </ConfirmDialog>
        </div>
    );
}

export default HistoryPage;
