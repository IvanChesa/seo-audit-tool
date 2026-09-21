import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createAudit, deleteAudit, getAudit, listAudits } from './audits';
import apiClient from './client';

vi.mock('./client', () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

describe('audits API', () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    it('creates an audit and unwraps the resource', async () => {
        apiClient.post.mockResolvedValue({ data: { data: { id: 7, status: 'pending' } } });

        await expect(createAudit('https://example.com')).resolves.toEqual({
            id: 7,
            status: 'pending',
        });
        expect(apiClient.post).toHaveBeenCalledWith('/audits', { url: 'https://example.com' });
    });

    it('fetches one audit with an abort signal', async () => {
        const controller = new AbortController();
        apiClient.get.mockResolvedValue({ data: { data: { id: 7 } } });

        await expect(getAudit(7, { signal: controller.signal })).resolves.toEqual({ id: 7 });
        expect(apiClient.get).toHaveBeenCalledWith('/audits/7', { signal: controller.signal });
    });

    it('lists audits sending only the filters in use', async () => {
        apiClient.get.mockResolvedValue({ data: { data: [{ id: 1 }], meta: { total: 1 } } });

        const result = await listAudits({
            page: 2,
            perPage: 5,
            status: 'failed',
            search: '  blog ',
        });

        expect(result).toEqual({ items: [{ id: 1 }], meta: { total: 1 } });
        expect(apiClient.get).toHaveBeenCalledWith('/audits', {
            params: { page: 2, per_page: 5, status: 'failed', search: 'blog' },
            signal: undefined,
        });

        await listAudits();
        expect(apiClient.get).toHaveBeenLastCalledWith('/audits', {
            params: { page: 1, per_page: 10 },
            signal: undefined,
        });
    });

    it('deletes an audit', async () => {
        apiClient.delete.mockResolvedValue({ status: 204 });

        await deleteAudit(3);

        expect(apiClient.delete).toHaveBeenCalledWith('/audits/3');
    });

    it('propagates request errors', async () => {
        apiClient.post.mockRejectedValue(new Error('boom'));

        await expect(createAudit('https://example.com')).rejects.toThrow('boom');
    });
});
