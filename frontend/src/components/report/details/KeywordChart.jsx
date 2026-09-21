import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/**
 * Visual version of the terms table. Recharts is heavy, so this component is
 * lazy-loaded; the table next to it is the accessible version of the data.
 */
function KeywordChart({ terms }) {
    return (
        <div className="chart" aria-hidden="true">
            <ResponsiveContainer width="100%" height={260}>
                <BarChart
                    data={terms}
                    layout="vertical"
                    margin={{ top: 4, right: 16, left: 8, bottom: 4 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        stroke="var(--border)"
                        horizontal={false}
                    />
                    <XAxis
                        type="number"
                        allowDecimals={false}
                        tick={{ fill: 'var(--text-muted)', fontSize: 12 }}
                    />
                    <YAxis
                        type="category"
                        dataKey="term"
                        width={110}
                        tick={{ fill: 'var(--text)', fontSize: 12 }}
                    />
                    <Tooltip
                        formatter={(value, _name, item) => [
                            `${value} veces (${item.payload.density} %)`,
                            'Frecuencia',
                        ]}
                        contentStyle={{
                            background: 'var(--surface)',
                            border: '1px solid var(--border)',
                            borderRadius: 8,
                            color: 'var(--text)',
                        }}
                    />
                    <Bar dataKey="count" fill="var(--accent)" radius={[0, 4, 4, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

export default KeywordChart;
