<script lang="ts">
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { page } from '$app/stores';
    import { authFetch } from '$lib/auth';

    const id = $derived($page.params.id);

    let name = $state('');
    let slug = $state('');
    let error = $state('');
    let loading = $state(false);
    let pageLoading = $state(true);

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
            const res = await authFetch(`http://localhost:8000/api/admin/categories/${id}`);
            const data = await res.json();
            name = data.name;
            slug = data.slug;
        } catch (e) {
            error = 'Nem sikerült betölteni a kategóriát.';
        } finally {
            pageLoading = false;
        }
    });

    async function save() {
        if (!name.trim()) {
            error = 'A kategória neve kötelező.';
            return;
        }

        loading = true;
        error = '';

        try {
            const res = await authFetch(`http://localhost:8000/api/admin/categories/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name.trim(),
                    slug: slug.trim() || slugify(name.trim()),
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                error = data.error ?? 'Hiba történt.';
                return;
            }

            goto('/admin/categories');
        } catch (e) {
            error = 'Nem sikerült menteni.';
        } finally {
            loading = false;
        }
    }
</script>

<main class="p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="/admin/categories" class="text-indigo-600 hover:underline text-sm">← Vissza</a>
        <h1 class="text-2xl font-bold text-gray-800">Kategória szerkesztése</h1>
    </div>

    {#if pageLoading}
        <p class="text-gray-400">Betöltés...</p>
    {:else}
        <div class="bg-white rounded-2xl shadow p-8 flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Név</label>
                <input
                    type="text"
                    bind:value={name}
                    oninput={() => { slug = slugify(name); }}
                    placeholder="Kategória neve"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Slug</label>
                <input
                    type="text"
                    bind:value={slug}
                    placeholder="kategoria-neve"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
                <p class="text-xs text-gray-400">Automatikusan generálódik a névből, de felülírható.</p>
            </div>

            {#if error}
                <p class="text-red-500 text-sm">{error}</p>
            {/if}

            <button
                onclick={save}
                disabled={loading}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40"
            >
                {loading ? 'Mentés...' : 'Mentés'}
            </button>
        </div>
    {/if}
</main>
