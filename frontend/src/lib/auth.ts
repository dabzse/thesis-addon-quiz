import { writable } from 'svelte/store';
import { get } from 'svelte/store';

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuthState {
    user: User | null;
    token: string | null;
}

const stored = typeof localStorage !== 'undefined'
    ? localStorage.getItem('auth')
    : null;

const initial: AuthState = stored
    ? JSON.parse(stored)
    : { user: null, token: null };

export const auth = writable<AuthState>(initial);

auth.subscribe(value => {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('auth', JSON.stringify(value));
    }
});

export function setAuth(user: User, token: string) {
    auth.set({ user, token });
}

export function clearAuth() {
    auth.set({ user: null, token: null });
}

export async function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const { token } = get(auth);

    const res = await fetch(url, {
        ...options,
        headers: {
            ...options.headers,
            Authorization: `Bearer ${token}`,
        },
    });

    if (res.status === 401) {
        clearAuth();
        window.location.href = '/admin';
    }

    return res;
}
