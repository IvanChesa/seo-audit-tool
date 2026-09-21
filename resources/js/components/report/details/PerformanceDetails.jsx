function PerformanceDetails({ data = {} }) {
    const strategy = data.strategy === 'desktop' ? 'escritorio' : 'móvil';

    return (
        <div className="details">
            <p className="muted">
                Fuente: {data.source ?? 'PageSpeed Insights'} · dispositivo {strategy}. Los datos de
                laboratorio pueden variar entre ejecuciones.
            </p>
        </div>
    );
}

export default PerformanceDetails;
