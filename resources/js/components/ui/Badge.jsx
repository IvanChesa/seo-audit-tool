import Icon from './Icon';

/**
 * Small status label: tone (colour) + icon + text. `label` is always visible
 * so the information never relies on colour alone.
 */
function Badge({ tone = 'neutral', icon, label, srPrefix, className = '' }) {
    return (
        <span className={`badge badge--${tone} ${className}`}>
            {icon && <Icon name={icon} />}
            {srPrefix && <span className="visually-hidden">{srPrefix} </span>}
            {label}
        </span>
    );
}

export default Badge;
