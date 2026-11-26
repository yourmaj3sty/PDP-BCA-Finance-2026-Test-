import { computed, ref } from 'vue';
import api, { TOKEN_KEY, USER_KEY } from '../services/api.js';

/** @typedef {import('../types/models.js').User} User */

/** @type {import('vue').Ref<User | null>} */
const currentUser = ref(readStoredUser());

/** @type {import('vue').Ref<string | null>} */
const token = ref(localStorage.getItem(TOKEN_KEY));

// true setelah /me selesai dicek sekali, supaya router tidak memanggilnya di setiap navigasi.
const hydrated = ref(false);

/**
 * @returns {User | null}
 */
function readStoredUser() {
    try {
        const raw = localStorage.getItem(USER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

/**
 * @param {User | null} user
 * @param {string | null} authToken
 */
function persistSession(user, authToken) {
    currentUser.value = user;
    token.value = authToken;

    if (user && authToken) {
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        localStorage.setItem(TOKEN_KEY, authToken);
    } else {
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem(TOKEN_KEY);
    }
}

// State disimpan di level modul, jadi semua komponen berbagi sesi yang sama.
export function useAuth() {
    const isAuthenticated = computed(() => Boolean(token.value && currentUser.value));

    /**
     * @param {string} loginIdentifier
     * @param {string} password
     * @returns {Promise<User>}
     */
    async function login(loginIdentifier, password) {
        const response = await api.post('/login', {
            login: loginIdentifier,
            password,
        });

        const payload = response.data?.data;
        persistSession(payload.user, payload.token);
        hydrated.value = true;
        return payload.user;
    }

    async function logout() {
        try {
            if (token.value) {
                await api.post('/logout');
            }
        } catch {
            // Token mungkin sudah tidak berlaku di server; sesi lokal tetap dibersihkan.
        } finally {
            persistSession(null, null);
        }
    }

    /**
     * @returns {Promise<User | null>}
     */
    async function fetchMe() {
        if (!token.value) {
            hydrated.value = true;
            return null;
        }

        try {
            const response = await api.get('/me');
            const user = response.data?.data ?? null;
            currentUser.value = user;
            if (user) {
                localStorage.setItem(USER_KEY, JSON.stringify(user));
            }
            return user;
        } catch {
            persistSession(null, null);
            return null;
        } finally {
            hydrated.value = true;
        }
    }

    /**
     * @param {...import('../types/models.js').UserRole} roles
     * @returns {boolean}
     */
    function hasRole(...roles) {
        return currentUser.value ? roles.includes(currentUser.value.role) : false;
    }

    return {
        user: currentUser,
        token,
        hydrated,
        isAuthenticated,
        login,
        logout,
        fetchMe,
        hasRole,
    };
}

