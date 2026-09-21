import apiClient from './client';

/**
 * Creates an audit for the given URL. The API validates and normalises it
 * and answers with the pending audit.
 */
export async function createAudit(url) {
    const response = await apiClient.post('/audits', { url });
    return response.data.data;
}

/**
 * Full audit with progress, sections and issues.
 */
export async function getAudit(id, { signal } = {}) {
    const response = await apiClient.get(`/audits/${encodeURIComponent(id)}`, { signal });
    return response.data.data;
}

/**
 * Paginated history. Empty filters are not sent.
 */
export async function listAudits(
    { page = 1, perPage = 10, status = '', search = '' } = {},
    { signal } = {},
) {
    const params = { page, per_page: perPage };

    if (status) params.status = status;
    if (search.trim()) params.search = search.trim();

    const response = await apiClient.get('/audits', { params, signal });

    return {
        items: response.data.data,
        meta: response.data.meta,
    };
}

export async function deleteAudit(id) {
    await apiClient.delete(`/audits/${encodeURIComponent(id)}`);
}
