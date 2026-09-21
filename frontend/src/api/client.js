import { create } from 'axios';

/** Base URL of the Laravel API, configurable per environment (see .env.example). */
export const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost/api').replace(/\/+$/, '');

const apiClient = create({
    baseURL: API_URL,
    timeout: 15000,
    headers: {
        Accept: 'application/json',
    },
});

export default apiClient;
