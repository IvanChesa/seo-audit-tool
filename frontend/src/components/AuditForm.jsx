import { useId, useState } from 'react';
import { createAudit } from '../api/audits';
import { describeApiError } from '../api/errors';
import { prepareUrl } from '../lib/url';

/**
 * URL form. Validates obvious mistakes locally and shows the API's own
 * validation message (e.g. private addresses) next to the field.
 */
function AuditForm({ onCreated }) {
    const [value, setValue] = useState('');
    const [error, setError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const inputId = useId();
    const hintId = useId();
    const errorId = useId();

    async function handleSubmit(event) {
        event.preventDefault();

        const { url, error: validationError } = prepareUrl(value);

        if (validationError) {
            setError(validationError);
            return;
        }

        setError(null);
        setSubmitting(true);

        try {
            const audit = await createAudit(url);
            onCreated(audit);
        } catch (requestError) {
            const info = describeApiError(requestError);
            setError(info.fieldErrors.url ?? info.message);
            setSubmitting(false);
        }
    }

    return (
        <form className="audit-form" onSubmit={handleSubmit} noValidate aria-busy={submitting}>
            <label className="audit-form__label" htmlFor={inputId}>
                URL de la página
            </label>
            <p id={hintId} className="audit-form__hint">
                Por ejemplo, <span className="mono">https://ejemplo.com/blog</span>. Si no indicas
                el protocolo se usará https://.
            </p>
            <div className="audit-form__row">
                <input
                    id={inputId}
                    name="url"
                    type="text"
                    inputMode="url"
                    autoComplete="url"
                    spellCheck="false"
                    autoCapitalize="none"
                    className="input"
                    placeholder="https://ejemplo.com"
                    value={value}
                    onChange={(event) => {
                        setValue(event.target.value);
                        if (error) setError(null);
                    }}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? `${hintId} ${errorId}` : hintId}
                    disabled={submitting}
                />
                <button type="submit" className="button button--primary" disabled={submitting}>
                    {submitting ? 'Creando auditoría…' : 'Analizar'}
                </button>
            </div>
            {error && (
                <p id={errorId} className="field-error" role="alert">
                    {error}
                </p>
            )}
        </form>
    );
}

export default AuditForm;
