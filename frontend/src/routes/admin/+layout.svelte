<script lang="ts">
    import { goto } from '$app/navigation';
    import { page } from '$app/stores';
    import { auth, clearAuth } from '$lib/auth';
    import { derived } from 'svelte/store';

    const isLoginPage = derived(page, $page => $page.url.pathname === '/admin');

    $effect(() => {
        if (!$isLoginPage && !$auth.token) {
            goto('/admin');
        }
    });

    async function logout() {
        await fetch('http://localhost:8000/api/auth/logout', {
            method: 'POST',
            headers: { Authorization: `Bearer ${$auth.token}` },
        });
        clearAuth();
        goto('/admin');
    }
</script>

{#if $isLoginPage || $auth.token}
    {#if !$isLoginPage}
        <nav class="bg-white border-b px-8 py-4 flex justify-between items-center">
            <span class="font-semibold text-indigo-700">Kvíz Admin</span>
            <div class="flex items-center gap-6">
                <a href="/admin/dashboard" class="text-gray-600 hover:text-indigo-600 text-sm">Dashboard</a>
                <a href="/admin/questions" class="text-gray-600 hover:text-indigo-600 text-sm">Kérdések</a>
                <a href="/admin/categories" class="text-gray-600 hover:text-indigo-600 text-sm">Kategóriák</a>
                <a href="/admin/settings" class="text-gray-600 hover:text-indigo-600 text-sm">Beállítások</a>
                <button
                    onclick={logout}
                    class="text-sm text-red-500 hover:text-red-700 transition"
                >
                    Kilépés
                </button>
            </div>
        </nav>
    {/if}

    <slot />
{/if}
