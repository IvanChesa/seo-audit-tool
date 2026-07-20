import { useState } from 'react';
import AuditForm from './components/AuditForm';
import AuditResult from './components/AuditResult';
import './App.css';

function App() {
  const [currentAuditId, setCurrentAuditId] = useState(null);

  const handleAuditCreated = (audit) => {
    setCurrentAuditId(audit.id);
  };

  const handleNewAudit = () => {
    setCurrentAuditId(null);
  };

  return (
    <div className="App">
      <h1>Herramienta de Auditoría SEO</h1>

      {!currentAuditId ? (
        <AuditForm onAuditCreated={handleAuditCreated} />
      ) : (
        <div>
          <button onClick={handleNewAudit}>← Analizar otra URL</button>
          <AuditResult auditId={currentAuditId} />
        </div>
      )}
    </div>
  );
}

export default App;