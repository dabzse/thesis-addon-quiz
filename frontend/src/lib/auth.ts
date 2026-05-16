import { writable, get } from 'svelte/store';

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuthState {
    user: User | null;
    token: string | null;
}

const stored = typeof localStorage === 'undefined'
    ? null
    : localStorage.getItem('auth');

const initial: AuthState = stored
    ? JSON.parse(stored)
    : { user: null, token: null };

export const auth = writable<AuthState>(initial);

auth.subscribe(value => {
    if (typeof localStorage === 'undefined') return;
    localStorage.setItem('auth', JSON.stringify(value));
});

export function setAuth(user: User, token: string) {
    auth.set({ user, token });
}

export function clearAuth() {
    auth.set({ user: null, token: null });
}

// Login redirect path — must match svelte.config.js paths.base + '/admin'
// Bejelentkezési átirányítási útvonal — meg kell egyeznie a svelte.config.js paths.base + '/admin'-nal
const LOGIN_PATH = '/quiz/admin';

export async function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const state = get(auth);

    const res = await fetch(url, {
        ...options,
        headers: {
            ...options.headers,
            Authorization: `Bearer ${state.token}`,
        },
    });

    if (res.status === 401) {
        clearAuth();
        // Redirect to login page (guard for test environments)
        // Átirányítás a bejelentkezési oldalra (tesztkörnyezetben nem elérhető)
        if (globalThis.location !== undefined) {
            globalThis.location.href = LOGIN_PATH;
        }
    }

    return res;
}
