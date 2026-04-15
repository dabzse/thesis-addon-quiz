<script lang="ts">
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { fetchCategories } from '$lib/api';
    import { userData } from '$lib/user';
    import type { Category } from '$lib/types';
    import { get } from 'svelte/store';

    let categories: Category[] = $state([]);
    let loading = $state(true);
    let error = $state('');

    const selectedYear = new Date().getFullYear();

    // Ha nincs belépőjegy, vissza a főoldalra
    onMount(async () => {
        const user = get(userData);
        if (!user.ticket) {
            goto('/');
            return;
        }

        try {
            categories = await fetchCategories();
        } catch (e) {
            error = 'Nem sikerült betölteni a kategóriákat.';
        } finally {
            loading = false;
        }
    });
</script>

<main class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-8">
    <h1 class="text-4xl font-bold text-gray-800 mb-2">Kvíz</h1>
    <p class="text-gray-500 mb-2">Válassz egy kategóriát!</p>
    <p class="text-gray-400 text-sm mb-8">{selectedYear}</p>

    {#if loading}
        <p class="text-gray-400">Betöltés...</p>
    {:else if error}
        <p class="text-red-500">{error}</p>
    {:else}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 w-full max-w-3xl">
            {#each categories as category (category.id)}
                <a
                    href="/quiz/{category.slug}"
                    data-sveltekit-preload-data="hover"
                    class="bg-white rounded-2xl shadow p-6 hover:shadow-md hover:bg-indigo-50 transition text-center"
                >
                    <h2 class="text-xl font-semibold text-indigo-700">{category.name}</h2>
                </a>
            {/each}

            <a
                href="/quiz/random"
                data-sveltekit-preload-data="hover"
                class="bg-indigo-600 rounded-2xl shadow p-6 hover:bg-indigo-700 transition text-center"
            >
                <h2 class="text-xl font-semibond text-white">🎲 Véletlenszerű</h2>
            </a>
        </div>
    {/if}
</main>
