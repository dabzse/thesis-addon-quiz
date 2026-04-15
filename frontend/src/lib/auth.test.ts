import { beforeEach, describe, expect, it, vi } from 'vitest';
import { get } from 'svelte/store';
import { auth, authFetch, clearAuth, setAuth } from './auth';

describe('auth store', () => {
	beforeEach(() => {
		clearAuth();
		(globalThis as typeof globalThis & { window?: { location: { href: string } } }).window = {
			location: { href: '/initial' },
		};
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

		const fetchMock = vi.fn().mockResolvedValue({ status: 401 });
		vi.stubGlobal('fetch', fetchMock);

		await authFetch('/api/private');

		expect(get(auth).token).toBeNull();
		expect((globalThis as typeof globalThis & { window: { location: { href: string } } }).window.location.href).toBe('/admin');
	});

	it('authFetch keeps auth state on successful requests', async () => {
		setAuth({ id: 1, name: 'Admin', email: 'admin@local.host' }, 'token-abc');

		const fetchMock = vi.fn().mockResolvedValue({ status: 200 });
		vi.stubGlobal('fetch', fetchMock);

		const response = await authFetch('/api/private');

		expect(response.status).toBe(200);
		expect(get(auth).token).toBe('token-abc');
		expect((globalThis as typeof globalThis & { window: { location: { href: string } } }).window.location.href).toBe('/initial');
	});
});
