/**
 * API payloads shaped exactly like the Laravel resources (AuditResource and
 * AuditSummaryResource), used by component and page tests.
 */

const SECTION_LABELS = {
    technical: 'Técnico',
    meta: 'Metadatos',
    headings: 'Encabezados',
    content: 'Contenido',
    links: 'Enlaces',
    performance: 'Rendimiento',
};

const WEIGHTS = { technical: 25, meta: 20, headings: 15, content: 15, links: 15, performance: 10 };

export function progress(statuses = {}) {
    const steps = [
        { key: 'fetch', label: 'Descarga de la página', status: statuses.fetch ?? 'completed' },
        ...Object.keys(SECTION_LABELS).map((key) => ({
            key,
            label: SECTION_LABELS[key],
            status: statuses[key] ?? 'completed',
        })),
    ];
    const done = steps.filter((step) =>
        ['completed', 'skipped', 'failed'].includes(step.status),
    ).length;

    return {
        completed_steps: done,
        total_steps: steps.length,
        percentage: Math.round((done / steps.length) * 100),
        steps,
    };
}

const issues = {
    missingH1: {
        code: 'missing_h1',
        severity: 'high',
        title: 'La página no tiene un encabezado H1',
        evidence: 'Hay 4 encabezados, pero ninguno es H1.',
        recommendation: 'Añade un único <h1> que resuma el tema principal de la página.',
    },
    brokenLinks: {
        code: 'broken_internal_links',
        severity: 'high',
        title: 'Hay enlaces internos rotos',
        evidence: '1 enlace(s) con error: https://example.com/roto (404).',
        recommendation: 'Corrige o elimina los enlaces rotos.',
    },
    canonical: {
        code: 'missing_canonical',
        severity: 'medium',
        title: 'No hay URL canónica',
        evidence: null,
        recommendation: 'Añade <link rel="canonical">.',
    },
    openGraph: {
        code: 'incomplete_open_graph',
        severity: 'low',
        title: 'Faltan etiquetas Open Graph',
        evidence: 'Faltan: og:image.',
        recommendation: 'Añade og:image.',
    },
};

function check(key, label, status, value) {
    return { key, label, status, value };
}

export function sections() {
    return [
        {
            key: 'technical',
            label: 'Técnico',
            weight: 25,
            status: 'completed',
            score: 100,
            score_rating: 'good',
            data: {
                checks: [
                    check('https', 'HTTPS', 'pass', 'Sí'),
                    check('lang', 'Idioma del documento', 'pass', 'es'),
                ],
                redirects: [{ url: 'http://example.com/', status: 301 }],
                final_url: 'https://example.com/',
                robots_txt: {
                    url: 'https://example.com/robots.txt',
                    status: 'found',
                    sitemaps: ['https://example.com/sitemap.xml'],
                },
            },
            issues: [],
            error: null,
        },
        {
            key: 'meta',
            label: 'Metadatos',
            weight: 20,
            status: 'completed',
            score: 85,
            score_rating: 'needs_improvement',
            data: {
                checks: [check('title', 'Título (<title>)', 'pass', '44 caracteres')],
                title: 'Guía para cuidar plantas de interior en casa',
                title_length: 44,
                meta_description: 'Consejos de riego.',
                meta_description_length: 18,
                canonical: null,
                robots: [],
                open_graph: { 'og:title': 'Guía', 'og:description': null, 'og:image': null },
            },
            issues: [
                { section: 'meta', ...issues.canonical },
                { section: 'meta', ...issues.openGraph },
            ],
            error: null,
        },
        {
            key: 'headings',
            label: 'Encabezados',
            weight: 15,
            status: 'completed',
            score: 80,
            score_rating: 'needs_improvement',
            data: {
                checks: [check('h1', 'Encabezado H1', 'fail', 'Ninguno')],
                counts: { h1: 0, h2: 3, h3: 1, h4: 0, h5: 0, h6: 0 },
                headings: [
                    { level: 2, text: 'Riego' },
                    { level: 3, text: 'Señales' },
                ],
                truncated: false,
            },
            issues: [issues.missingH1],
            error: null,
        },
        {
            key: 'content',
            label: 'Contenido',
            weight: 15,
            status: 'completed',
            score: 100,
            score_rating: 'good',
            data: {
                checks: [check('word_count', 'Palabras en el contenido', 'pass', '420')],
                word_count: 420,
                top_terms: [
                    { term: 'plantas', count: 9, density: 2.14 },
                    { term: 'riego', count: 6, density: 1.43 },
                ],
                images: { total: 2, missing_alt: 0, decorative: 1, missing_alt_examples: [] },
            },
            issues: [],
            error: null,
        },
        {
            key: 'links',
            label: 'Enlaces',
            weight: 15,
            status: 'completed',
            score: 80,
            score_rating: 'needs_improvement',
            data: {
                checks: [check('broken_links', 'Enlaces rotos', 'fail', '1 de 3 comprobados')],
                totals: {
                    links: 4,
                    unique: 3,
                    internal: 2,
                    external: 1,
                    nofollow: 0,
                    without_text: 0,
                },
                checked: {
                    limit: 30,
                    checked: 3,
                    broken: 1,
                    restricted: 0,
                    skipped: 0,
                    unchecked: 0,
                },
                broken_links: [
                    {
                        url: 'https://example.com/roto',
                        is_internal: true,
                        status_code: 404,
                        error: null,
                        link_text: 'Roto',
                    },
                ],
            },
            issues: [issues.brokenLinks],
            error: null,
        },
        {
            key: 'performance',
            label: 'Rendimiento',
            weight: 10,
            status: 'skipped',
            score: null,
            score_rating: null,
            data: {},
            issues: [],
            error: {
                code: 'pagespeed_not_configured',
                message:
                    'La medición de rendimiento no se ha ejecutado porque no hay una clave de PageSpeed Insights configurada (PAGESPEED_API_KEY).',
            },
        },
    ];
}

export function completedAudit(overrides = {}) {
    return {
        id: 42,
        url: 'https://example.com/',
        host: 'example.com',
        final_url: 'https://example.com/',
        status: 'completed',
        score: 87,
        score_rating: 'needs_improvement',
        error: null,
        created_at: '2026-09-21T10:00:00+00:00',
        started_at: '2026-09-21T10:00:01+00:00',
        finished_at: '2026-09-21T10:00:43+00:00',
        http_status: 200,
        legacy: false,
        progress: progress({ performance: 'skipped' }),
        score_breakdown: {
            weighted_average: 87,
            critical_cap: 49,
            critical_cap_applied: false,
            sections: Object.keys(WEIGHTS).map((key) => ({
                key,
                label: SECTION_LABELS[key],
                weight: WEIGHTS[key],
                effective_weight:
                    key === 'performance' ? 0 : Number(((WEIGHTS[key] / 90) * 100).toFixed(1)),
                score: key === 'performance' ? null : 90,
                counted: key !== 'performance',
            })),
        },
        issues_summary: { critical: 0, high: 2, medium: 1, low: 1, total: 4 },
        issues: [
            { section: 'headings', section_label: 'Encabezados', ...issues.missingH1 },
            { section: 'links', section_label: 'Enlaces', ...issues.brokenLinks },
            { section: 'meta', section_label: 'Metadatos', ...issues.canonical },
            { section: 'meta', section_label: 'Metadatos', ...issues.openGraph },
        ],
        sections: sections(),
        ...overrides,
    };
}

export function processingAudit(overrides = {}) {
    return completedAudit({
        status: 'processing',
        score: null,
        score_rating: null,
        finished_at: null,
        progress: progress({
            technical: 'running',
            content: 'running',
            links: 'running',
            performance: 'running',
        }),
        issues: [],
        issues_summary: { critical: 0, high: 0, medium: 0, low: 0, total: 0 },
        sections: [],
        ...overrides,
    });
}

export function failedAudit(overrides = {}) {
    return completedAudit({
        status: 'failed',
        score: null,
        score_rating: null,
        http_status: null,
        error: {
            code: 'http_error',
            message:
                'La página respondió con el código HTTP 404. Solo se pueden auditar páginas que respondan correctamente (2xx).',
        },
        progress: progress({
            fetch: 'failed',
            technical: 'not_run',
            meta: 'not_run',
            headings: 'not_run',
            content: 'not_run',
            links: 'not_run',
            performance: 'not_run',
        }),
        issues: [],
        issues_summary: { critical: 0, high: 0, medium: 0, low: 0, total: 0 },
        sections: [],
        ...overrides,
    });
}

export function legacyAudit(overrides = {}) {
    return completedAudit({
        legacy: true,
        score: 64,
        progress: null,
        score_breakdown: null,
        issues_summary: null,
        issues: [],
        sections: [],
        ...overrides,
    });
}

export function summary(id, overrides = {}) {
    return {
        id,
        url: `https://site-${id}.example.com/`,
        host: `site-${id}.example.com`,
        final_url: `https://site-${id}.example.com/`,
        status: 'completed',
        score: 80,
        score_rating: 'needs_improvement',
        error: null,
        created_at: '2026-09-21T10:00:00+00:00',
        finished_at: '2026-09-21T10:01:00+00:00',
        ...overrides,
    };
}

export function page(
    items,
    { currentPage = 1, lastPage = 1, total = items.length, perPage = 10 } = {},
) {
    return {
        items,
        meta: { current_page: currentPage, last_page: lastPage, total, per_page: perPage },
    };
}

/** Axios-like error with an HTTP response. */
export function httpError(status, data = {}) {
    const error = new Error(`Request failed with status code ${status}`);
    error.response = { status, data };
    return error;
}

/** Axios-like network error (no response). */
export function networkError() {
    const error = new Error('Network Error');
    error.code = 'ERR_NETWORK';
    return error;
}
