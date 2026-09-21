import { STEP_STATUS, lookup } from '../lib/labels';
import Icon from './ui/Icon';

/**
 * Real progress reported by the API: one step per job (download + one per
 * report section), with a progress bar and a polite live announcement.
 */
function AnalysisProgress({ progress, status }) {
    const steps = progress?.steps ?? [];
    const done = progress?.completed_steps ?? 0;
    const total = progress?.total_steps ?? steps.length;
    const percentage = progress?.percentage ?? 0;
    const heading =
        status === 'pending' ? 'En cola: el análisis empezará en breve' : 'Analizando la página…';

    return (
        <section className="card progress" aria-labelledby="progress-title">
            <h2 id="progress-title" className="card__title">
                <Icon name="spinner" /> {heading}
            </h2>
            <div
                className="progress__bar"
                role="progressbar"
                aria-label="Progreso de la auditoría"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percentage}
                aria-valuetext={`${done} de ${total} pasos completados`}
            >
                <div className="progress__fill" style={{ width: `${percentage}%` }} />
            </div>
            <p className="progress__summary" aria-live="polite">
                {done} de {total} pasos completados
            </p>
            <ol className="progress__steps">
                {steps.map((step) => {
                    const info = lookup(STEP_STATUS, step.status);

                    return (
                        <li key={step.key} className={`progress__step tone--${info.tone}`}>
                            <Icon name={info.icon} />
                            <span className="progress__step-label">{step.label}</span>
                            <span className="progress__step-status">{info.label}</span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

export default AnalysisProgress;
