import { Component } from 'react';
import Alert from './ui/Alert';

/**
 * Last line of defence against rendering errors (e.g. an unexpected API
 * payload): shows a friendly message instead of a blank page. The layout
 * gives it a new key on every navigation, so the next page starts clean.
 */
class ErrorBoundary extends Component {
    state = { error: null };

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error('Unexpected rendering error', error, info.componentStack);
    }

    render() {
        if (!this.state.error) {
            return this.props.children;
        }

        return (
            <Alert
                type="error"
                title="Algo ha salido mal al mostrar esta página"
                action={
                    <button
                        type="button"
                        className="button"
                        onClick={() => window.location.reload()}
                    >
                        Recargar la página
                    </button>
                }
            >
                <p>
                    Se ha producido un error inesperado. Puedes recargar la página o volver al{' '}
                    <a href="/">inicio</a>.
                </p>
            </Alert>
        );
    }
}

export default ErrorBoundary;
