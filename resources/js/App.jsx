import { Route, Routes } from 'react-router-dom';
import Layout from './components/Layout';
import AuditPage from './pages/AuditPage';
import HistoryPage from './pages/HistoryPage';
import HomePage from './pages/HomePage';
import NotFoundPage from './pages/NotFoundPage';

function App() {
    return (
        <Routes>
            <Route element={<Layout />}>
                <Route index element={<HomePage />} />
                <Route path="audits/:id" element={<AuditPage />} />
                <Route path="history" element={<HistoryPage />} />
                <Route path="*" element={<NotFoundPage />} />
            </Route>
        </Routes>
    );
}

export default App;
