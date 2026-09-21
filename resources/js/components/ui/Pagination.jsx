/**
 * Previous / next pagination with the current position as text.
 */
function Pagination({ page, lastPage, total, onChange }) {
    if (!lastPage || lastPage <= 1) return null;

    return (
        <nav className="pagination" aria-label="Paginación del historial">
            <button
                type="button"
                className="button button--secondary"
                onClick={() => onChange(page - 1)}
                disabled={page <= 1}
            >
                Anterior
            </button>
            <p className="pagination__status" aria-live="polite">
                Página {page} de {lastPage}
                <span className="pagination__total"> · {total} auditorías</span>
            </p>
            <button
                type="button"
                className="button button--secondary"
                onClick={() => onChange(page + 1)}
                disabled={page >= lastPage}
            >
                Siguiente
            </button>
        </nav>
    );
}

export default Pagination;
