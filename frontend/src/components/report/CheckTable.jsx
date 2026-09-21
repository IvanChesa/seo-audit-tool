import { CHECK_HELP } from '../../lib/help';
import { CHECK_STATUS, lookup } from '../../lib/labels';
import Badge from '../ui/Badge';

/**
 * Metrics measured in a section, each with its value, an evaluation (icon +
 * text) and a short explanation of what it means.
 */
function CheckTable({ checks, caption }) {
    if (!Array.isArray(checks) || checks.length === 0) return null;

    return (
        <div className="table-wrapper">
            <table className="table checks">
                <caption className="visually-hidden">{caption}</caption>
                <thead>
                    <tr>
                        <th scope="col">Métrica</th>
                        <th scope="col">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    {checks.map((check) => {
                        const status = lookup(CHECK_STATUS, check.status);
                        const help = CHECK_HELP[check.key];

                        return (
                            <tr key={check.key}>
                                <th scope="row">
                                    <span className="checks__label">{check.label}</span>
                                    {help && <span className="checks__help">{help}</span>}
                                </th>
                                <td>
                                    <div className="checks__result">
                                        <span className="checks__value">{check.value ?? '—'}</span>
                                        <Badge
                                            tone={status.tone}
                                            icon={status.icon}
                                            label={status.label}
                                        />
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

export default CheckTable;
