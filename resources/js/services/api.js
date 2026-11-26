import axios from 'axios';

export const TOKEN_KEY = 'jkl_auth_token';
export const USER_KEY = 'jkl_auth_user';

const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const requestUrl = error?.config?.url ?? '';

        // Token kedaluwarsa/dicabut: bersihkan sesi lalu kembali ke login.
        // Halaman login dan tanda tangan publik menangani 401 sendiri.
        if (status === 401 && !requestUrl.includes('/login') && !requestUrl.includes('/public/')) {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
            if (window.location.pathname !== '/login') {
                window.location.assign('/login');
            }
        }

        return Promise.reject(error);
    },
);

/**
 * Ambil pesan utama dan error per field (pesan pertama saja) dari respons validasi Laravel.
 *
 * @param {unknown} err
 * @param {string} [fallback='Terjadi kesalahan saat memproses permintaan.']
 * @returns {{ message: string, fieldErrors: Record<string, string> }}
 */
export function parseApiError(err, fallback = 'Terjadi kesalahan saat memproses permintaan.') {
    const axiosErr = /** @type {any} */ (err);
    const data = axiosErr?.response?.data;
    /** @type {Record<string, string>} */
    const fieldErrors = {};

    if (data?.errors && typeof data.errors === 'object') {
        for (const [key, messages] of Object.entries(data.errors)) {
            if (Array.isArray(messages) && messages.length > 0) {
                fieldErrors[key] = String(messages[0]);
            }
        }
    }

    const message = typeof data?.message === 'string' && data.message.trim() !== ''
        ? data.message
        : fallback;

    return { message, fieldErrors };
}

export default api;

