import apiClient from './client';

/**
 * Create a new audit for the given URL.
 */
export const createAudit = async (url) => {
    const response = await apiClient.post('/audits', { url });
    return response.data;
};

/**
 * Get a single audit by ID, including its results.
 */
export const getAudit = async (id) => {
    const response = await apiClient.get(`/audits/${id}`);
    return response.data;
};

/**
 * List all audits (paginated).
 */
export const listAudits = async () => {
    const response = await apiClient.get('/audits');
    return response.data;
};