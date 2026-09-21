import { Link } from 'react-router-dom';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

function NotFoundPage() {
    useDocumentTitle('Página no encontrada');

    return (
        <div className="page-message">
            <h1>Página no encontrada</h1>
            <p>La dirección no corresponde a ninguna página de la aplicación.</p>
            <p>
                <Link to="/">Volver al inicio</Link>
            </p>
        </div>
    );
}

export default NotFoundPage;
