import { useEffect, useRef } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import ErrorBoundary from './ErrorBoundary';

/**
 * Page chrome: skip link, header navigation, main landmark and footer.
 * After client-side navigation the focus moves to <main>, so keyboard and
 * screen-reader users start reading the new page from the top.
 */
function Layout() {
    const { pathname } = useLocation();
    const mainRef = useRef(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        mainRef.current?.focus();
        window.scrollTo?.(0, 0);
    }, [pathname]);

    return (
        <div className="app">
            <a className="skip-link" href="#contenido">
                Saltar al contenido
            </a>

            <header className="app-header">
                <div className="app-header__inner">
                    <Link to="/" className="brand">
                        <img src="/favicon.svg" alt="" width="28" height="28" />
                        <span>SEO Audit Tool</span>
                    </Link>
                    <nav aria-label="Principal">
                        <ul className="nav">
                            <li>
                                <NavLink to="/" end>
                                    Nueva auditoría
                                </NavLink>
                            </li>
                            <li>
                                <NavLink to="/history">Historial</NavLink>
                            </li>
                        </ul>
                    </nav>
                </div>
            </header>

            <main id="contenido" ref={mainRef} tabIndex={-1} className="app-main">
                <ErrorBoundary key={pathname}>
                    <Outlet />
                </ErrorBoundary>
            </main>

            <footer className="app-footer">
                <p>
                    Proyecto de portfolio ·{' '}
                    <a
                        href="https://github.com/IvanChesa/seo-audit-tool"
                        target="_blank"
                        rel="noreferrer"
                    >
                        código en GitHub
                        <span className="visually-hidden"> (se abre en una pestaña nueva)</span>
                    </a>
                </p>
            </footer>
        </div>
    );
}

export default Layout;
