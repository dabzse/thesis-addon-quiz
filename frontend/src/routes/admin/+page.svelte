<script lang="ts">
    import { goto } from '$app/navigation';
    import { setAuth } from '$lib/auth';
    import { auth, authFetch } from '$lib/auth';

    let email = $state('');
    let password = $state('');
    let error = $state('');
    let loading = $state(false);

    async function login() {
        if (!email.trim() || !password.trim()) {
            error = 'Kérjük töltsd ki az összes mezőt.';
            return;
        }

        loading = true;
        error = '';

        try {
            const res = await authFetch('http://localhost:8000/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
            });

            const data = await res.json();

            if (!res.ok) {
                error = data.error ?? 'Hiba történt.';
                return;
            }

            setAuth(data.user, data.token);
            goto('/admin/dashboard');
        } catch (e) {
            error = 'Nem sikerült csatlakozni a szerverhez.';
        } finally {
            loading = false;
        }
    }
</script>

<main class="min-h-screen bg-gray-50 flex items-center justify-center p-8">
    <div class="bg-white rounded-2xl shadow p-10 w-full max-w-md">
        <h1 class="text-2xl font-bold text-gray-800 mb-8 text-center">Admin belépés</h1>

        <div class="flex flex-col gap-4">
            <input
                type="email"
                placeholder="E-mail cím"
                bind:value={email}
                class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
            <input
                type="password"
                placeholder="Jelszó"
                bind:value={password}
                class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />

            {#if error}
                <p class="text-red-500 text-sm">{error}</p>
            {/if}

            <button
                onclick={login}
                disabled={loading}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40"
            >
                {loading ? 'Belépés...' : 'Belépés'}
            </button>
        </div>
    </div>
</main>
