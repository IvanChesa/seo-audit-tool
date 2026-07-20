import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid,
} from 'recharts';
import IssueList from './IssueList';

function KeywordsSection({ result }) {
    const { top_keywords: topKeywords = [], total_words: totalWords, issues = [], error } = result.data;

    if (error) {
        return <p className="section-error">No se pudo analizar las palabras clave ({error}).</p>;
    }

    return (
        <div>
            <p className="section-note">
                {totalWords} palabras analizadas (sin stopwords). Densidad = % de apariciones
                sobre el total.
            </p>

            <div className="keywords-chart">
                <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={topKeywords} margin={{ top: 8, right: 16, left: 0, bottom: 40 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                        <XAxis
                            dataKey="word"
                            angle={-40}
                            textAnchor="end"
                            interval={0}
                            tick={{ fill: 'var(--text)', fontSize: 13 }}
                        />
                        <YAxis tick={{ fill: 'var(--text)', fontSize: 13 }} allowDecimals={false} />
                        <Tooltip
                            formatter={(value, name, item) => [
                                `${value} veces (${item.payload.density}%)`,
                                'Frecuencia',
                            ]}
                            contentStyle={{
                                background: 'var(--surface)',
                                border: '1px solid var(--border)',
                                borderRadius: 8,
                                color: 'var(--text-h)',
                            }}
                        />
                        <Bar dataKey="count" fill="var(--accent)" radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <IssueList issues={issues} />
        </div>
    );
}

export default KeywordsSection;
