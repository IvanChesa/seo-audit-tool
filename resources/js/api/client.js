import { create } from 'axios';

// Laravel serves both this app and the API, so requests stay on the same origin.
const apiClient = create({
    baseURL: '/api',
    timeout: 15000,
    headers: {
        Accept: 'application/json',
    },
});

export default apiClient;
