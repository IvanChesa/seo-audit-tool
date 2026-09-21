import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';

/** Renders the current location so tests can assert navigation. */
function LocationDisplay() {
    const location = useLocation();

    return <div data-testid="location">{`${location.pathname}${location.search}`}</div>;
}

/**
 * Renders `element` at `path` inside a memory router. The current location is
 * always rendered so tests can check where the app navigated to.
 */
export function renderRoute(element, { path = '/', route = path } = {}) {
    return render(
        <MemoryRouter initialEntries={[route]}>
            <Routes>
                <Route path={path} element={element} />
                <Route path="*" element={<p>Otra página</p>} />
            </Routes>
            <LocationDisplay />
        </MemoryRouter>,
    );
}
