import { beforeEach, describe, expect, it, vi } from 'vitest';
import { get } from 'svelte/store';
import { auth, authFetch, clearAuth, setAuth } from './auth';

describe('auth store', () => {
    beforeEach(() => {
        clearAuth();
        // Clear location mock from previous tests
        delete (globalThis as Record<string, unknown>).location;
    });

    it('setAuth stores user and token', () => {
        setAuth({ id: 1, name: 'Admin', email: 'admin@example.com' }, 'token-123');

        const state = get(auth);
        expect(state.user?.email).toBe('admin@example.com');
        expect(state.token).toBe('token-123');
    });

    it('authFetch adds Authorization header', async () => {
        setAuth({ id: 1, name: 'Admin', email: 'admin@example.com' }, 'token-xyz');

        const fetchMock = vi.fn().mockResolvedValue({ status: 200 });
        vi.stubGlobal('fetch', fetchMock);

        await authFetch('/api/private', { headers: { 'X-Test': 'ok' } });

        expect(fetchMock).toHaveBeenCalledWith('/api/private', {
            headers: {
                'X-Test': 'ok',
                Authorization: 'Bearer token-xyz',
            },
        });
    });

    it('authFetch clears auth and redirects on 401', async () => {
        setAuth({ id: 1, name: 'Admin', email: 'admin@example.com' }, 'expired-token');

        (globalThis as Record<string, unknown>).location = { href: '/initial' };

        const fetchMock = vi.fn().mockResolvedValue({ status: 401 });
        vi.stubGlobal('fetch', fetchMock);

        await authFetch('/api/private');

        expect(get(auth).token).toBeNull();
        expect((globalThis as unknown as { location: { href: string } }).location.href).toBe('/quiz/admin');
    });

    it('authFetch keeps auth state on successful requests', async () => {
        setAuth({ id: 1, name: 'Admin', email: 'admin@local.host' }, 'token-abc');

        const fetchMock = vi.fn().mockResolvedValue({ status: 200 });
        vi.stubGlobal('fetch', fetchMock);

        const response = await authFetch('/api/private');

        expect(response.status).toBe(200);
        expect(get(auth).token).toBe('token-abc');
        // location should not have been touched (no redirect on 200)
        expect((globalThis as Record<string, unknown>).location).toBeUndefined();
    });
});
