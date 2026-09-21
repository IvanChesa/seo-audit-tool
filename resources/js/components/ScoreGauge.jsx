import { RATING, lookup } from '../lib/labels';

const RADIUS = 52;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * Circular gauge for a 0–100 score. The number and the rating text are
 * rendered as real text, so the gauge is readable without colour.
 */
function ScoreGauge({ score, rating, label = 'Puntuación SEO global' }) {
    const value = Math.max(0, Math.min(100, score ?? 0));
    const ratingInfo = rating ? lookup(RATING, rating) : null;
    const offset = CIRCUMFERENCE * (1 - value / 100);

    return (
        <figure className={`gauge gauge--${ratingInfo?.tone ?? 'neutral'}`}>
            <div className="gauge__visual">
                <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                    <circle className="gauge__track" cx="60" cy="60" r={RADIUS} />
                    <circle
                        className="gauge__value"
                        cx="60"
                        cy="60"
                        r={RADIUS}
                        strokeDasharray={CIRCUMFERENCE}
                        strokeDashoffset={offset}
                        transform="rotate(-90 60 60)"
                    />
                </svg>
                <p className="gauge__number">
                    <span className="gauge__score">{score ?? '—'}</span>
                    <span className="gauge__max">/100</span>
                </p>
            </div>
            <figcaption className="gauge__caption">
                <span className="gauge__label">{label}</span>
                {ratingInfo && <span className="gauge__rating">{ratingInfo.label}</span>}
            </figcaption>
        </figure>
    );
}

export default ScoreGauge;
