import Icon from './Icon';

const ICONS = { error: 'cross', warning: 'alert', info: 'info', success: 'check' };

/**
 * Inline message. Errors use role="alert" so they are announced immediately;
 * the rest use role="status" (polite).
 */
function Alert({ type = 'info', title, children, action }) {
    return (
        <div className={`alert alert--${type}`} role={type === 'error' ? 'alert' : 'status'}>
            <Icon name={ICONS[type]} className="alert__icon" />
            <div className="alert__body">
                {title && <p className="alert__title">{title}</p>}
                {children && <div className="alert__text">{children}</div>}
                {action && <div className="alert__action">{action}</div>}
            </div>
        </div>
    );
}

export default Alert;
