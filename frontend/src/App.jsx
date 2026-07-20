import { Routes, Route, NavLink, useNavigate, useParams } from 'react-router-dom';
import AuditForm from './components/AuditForm';
import AuditResult from './components/AuditResult';
import HistoryPage from './components/HistoryPage';
import './App.css';

function HomePage() {
  const navigate = useNavigate();

  return (
    <section className="home">
      <h2>Analiza el SEO de cualquier página</h2>
      <p className="home-subtitle">
        Meta etiquetas, encabezados, densidad de palabras clave, enlaces rotos
        y Core Web Vitals en un solo informe.
      </p>
      <AuditForm onAuditCreated={(audit) => navigate(`/audits/${audit.id}`)} />
    </section>
  );
}

function AuditPage() {
  const { id } = useParams();

  return <AuditResult auditId={id} />;
}

function App() {
  return (
    <div className="app">
      <header className="app-header">
        <NavLink to="/" className="app-logo">SEO Audit</NavLink>
        <nav>
          <NavLink to="/">Nueva auditoría</NavLink>
          <NavLink to="/history">Historial</NavLink>
        </nav>
      </header>

      <main className="app-main">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/audits/:id" element={<AuditPage />} />
          <Route path="/history" element={<HistoryPage />} />
        </Routes>
      </main>
    </div>
  );
}

export default App;
