import { Link, useNavigate } from 'react-router-dom';
import AuditForm from '../components/AuditForm';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

const FEATURES = [
    {
        title: 'Técnico',
        text: 'Código HTTP, HTTPS, redirecciones, idioma, viewport, robots.txt y sitemap.',
    },
    {
        title: 'Metadatos',
        text: 'Título, meta description, URL canónica, meta robots y Open Graph.',
    },
    { title: 'Encabezados', text: 'H1 y jerarquía H1–H6 sin saltos ni encabezados vacíos.' },
    { title: 'Contenido', text: 'Cantidad de texto, términos más frecuentes e imágenes sin alt.' },
    { title: 'Enlaces', text: 'Enlaces internos y externos y comprobación de enlaces rotos.' },
    {
        title: 'Rendimiento',
        text: 'Core Web Vitals de laboratorio con PageSpeed Insights (si hay clave configurada).',
    },
];

function HomePage() {
    const navigate = useNavigate();
    useDocumentTitle('Nueva auditoría');

    return (
        <div className="home">
            <section className="hero" aria-labelledby="home-title">
                <h1 id="home-title">Audita el SEO de cualquier página pública</h1>
                <p className="hero__lead">
                    Introduce una URL pública y obtén un informe con los problemas encontrados,
                    ordenados por severidad y con recomendaciones concretas para corregirlos.
                </p>
                <AuditForm onCreated={(audit) => navigate(`/audits/${audit.id}`)} />
            </section>

            <section aria-labelledby="features-title">
                <h2 id="features-title">Qué se analiza</h2>
                <ul className="feature-grid">
                    {FEATURES.map((feature) => (
                        <li key={feature.title} className="feature">
                            <h3>{feature.title}</h3>
                            <p>{feature.text}</p>
                        </li>
                    ))}
                </ul>
                <p className="muted">
                    ¿Ya has hecho auditorías? Consulta el <Link to="/history">historial</Link>.
                </p>
            </section>
        </div>
    );
}

export default HomePage;
