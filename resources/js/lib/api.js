import axios from 'axios';

const TOKEN_KEY = 'api_token';

export const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = getToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

/**
 * POST /api/v1/auth/login — guarda el token y devuelve el usuario.
 */
export async function apiLogin(credentials) {
    const { data } = await api.post('/auth/login', {
        device_name: 'spa',
        ...credentials,
    });

    setToken(data.token);

    return data.data;
}

/**
 * POST /api/v1/auth/logout — revoca el token actual.
 */
export async function apiLogout() {
    await api.post('/auth/logout');
    clearToken();
}
