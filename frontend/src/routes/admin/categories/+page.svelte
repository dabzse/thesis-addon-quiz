<script lang="ts">
    import { onMount } from 'svelte';
    import { authFetch } from '$lib/auth';

    interface Category {
        id: number;
        name: string;
        slug: string;
    }

    let categories: Category[] = $state([]);
    let loading = $state(true);
    let error = $state('');

    let showNewCategory = $state(false);
    let newName = $state('');
    let newSlug = $state('');
    let newError = $state('');
    let newLoading = $state(false);

    function slugify(text: string): string {
        return text
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    onMount(async () => {
        try {
            const res = await authFetch('http://localhost:8000/api/admin/categories');
            categories = await res.json();
        } catch (e) {
            error = 'Nem sikerült betölteni a kategóriákat.';
        } finally {
            loading = false;
        }
    });

    async function saveCategory() {
        if (!newName.trim()) {
            newError = 'A kategória neve kötelező.';
            return;
        }

        newLoading = true;
        newError = '';

        try {
            const res = await authFetch('http://localhost:8000/api/admin/categories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: newName.trim(),
                    slug: newSlug.trim() || slugify(newName.trim()),
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                newError = data.error ?? 'Hiba történt.';
                return;
            }

            categories = [...categories, { id: data.id, name: newName.trim(), slug: newSlug.trim() || slugify(newName.trim()) }];
            newName = '';
            newSlug = '';
            showNewCategory = false;
        } catch (e) {
            newError = 'Nem sikerült menteni.';
        } finally {
            newLoading = false;
        }
    }
</script>

<main class="p-8 max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Kategóriák</h1>
        <button
            onclick={() => { showNewCategory = !showNewCategory; newError = ''; }}
            class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition text-sm"
        >
            {showNewCategory ? 'Mégse' : '+ Új kategória'}
        </button>
    </div>

    {#if showNewCategory}
        <div class="bg-indigo-50 rounded-2xl p-6 mb-6 flex flex-col gap-4">
            <h2 class="text-lg font-semibold text-gray-700">Új kategória</h2>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Név</label>
                <input
                    type="text"
                    bind:value={newName}
                    oninput={() => { newSlug = slugify(newName); }}
                    placeholder="Kategória neve"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Slug</label>
                <input
                    type="text"
                    bind:value={newSlug}
                    placeholder="kategoria-neve"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white"
                />
                <p class="text-xs text-gray-400">Automatikusan generálódik a névből, de felülírható.</p>
            </div>

            {#if newError}
                <p class="text-red-500 text-sm">{newError}</p>
            {/if}

            <button
                onclick={saveCategory}
                disabled={newLoading}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40 self-start"
            >
                {newLoading ? 'Mentés...' : 'Mentés'}
            </button>
        </div>
    {/if}

    {#if loading}
        <p class="text-gray-400">Betöltés...</p>
    {:else if error}
        <p class="text-red-500">{error}</p>
    {:else if categories.length === 0}
        <p class="text-gray-400">Még nincsenek kategóriák.</p>
    {:else}
        <div class="flex flex-col gap-3">
            {#each categories as cat (cat.id)}
                <div class="bg-white rounded-xl shadow px-6 py-4 flex justify-between items-center gap-4">
                    <div>
                        <p class="text-gray-800 font-medium">{cat.name}</p>
                        <p class="text-sm text-gray-400">{cat.slug}</p>
                    </div>
                    <a
                        href="/admin/categories/{cat.id}/edit"
                        class="text-sm text-indigo-600 hover:underline"
                    >
                        Szerkesztés
                    </a>
                </div>
            {/each}
        </div>
    {/if}
</main>
