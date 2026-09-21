import { useEffect, useId, useRef } from 'react';

/**
 * Accessible confirmation built on the native <dialog>: showModal() traps
 * focus, makes the rest of the page inert and closes with Escape.
 */
function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    destructive = false,
    busy = false,
    onConfirm,
    onCancel,
}) {
    const dialogRef = useRef(null);
    const cancelRef = useRef(null);
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) {
            dialog.showModal();
            // The safe option gets the initial focus.
            cancelRef.current?.focus();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            className="dialog"
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
            onCancel={(event) => {
                event.preventDefault();
                if (!busy) onCancel();
            }}
        >
            <h2 id={titleId} className="dialog__title">
                {title}
            </h2>
            <div id={descriptionId} className="dialog__body">
                {children}
            </div>
            <div className="dialog__actions">
                <button
                    ref={cancelRef}
                    type="button"
                    className="button button--secondary"
                    onClick={onCancel}
                    disabled={busy}
                >
                    {cancelLabel}
                </button>
                <button
                    type="button"
                    className={`button ${destructive ? 'button--danger' : ''}`}
                    onClick={onConfirm}
                    disabled={busy}
                    aria-busy={busy}
                >
                    {busy ? 'Procesando…' : confirmLabel}
                </button>
            </div>
        </dialog>
    );
}

export default ConfirmDialog;
