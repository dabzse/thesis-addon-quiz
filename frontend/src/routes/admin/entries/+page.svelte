<a href="/admin/entries" class="text-gray-600 hover:text-indigo-600 text-sm">Nevezések</a>

<script lang="ts">
    import { onMount } from 'svelte';
    import { authFetch } from '$lib/auth';

    interface Entry {
        id: number;
        ticket_number: string;
        name: string | null;
        email: string | null;
        score: number;
        max_score: number;
        event_year: number;
        created_at: string;
        category: string | null;
    }

    let entries: Entry[] = $state([]);
    let loading = $state(true);
    let error = $state('');
    let selectedYear = $state(new Date().getFullYear());

    async function loadEntries() {
        loading = true;
        error = '';
        try {
            const res = await authFetch(`http://localhost:8000/api/admin/entries?year=${selectedYear}`);
            entries = await res.json();
        } catch (e) {
            error = 'Nem sikerült betölteni a nevezéseket.';
        } finally {
            loading = false;
        }
    }

    onMount(() => loadEntries());
</script>

<main class="p-8 max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Nevezések</h1>
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Év:</label>
            <input
                type="number"
                bind:value={selectedYear}
                min="2020"
                max="2099"
                class="border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-32"
            />
            <button
                onclick={loadEntries}
                class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition text-sm"
            >
                Szűrés
            </button>
        </div>
    </div>

    {#if loading}
        <p class="text-gray-400">Betöltés...</p>
    {:else if error}
        <p class="text-red-500">{error}</p>
    {:else if entries.length === 0}
        <p class="text-gray-400">Nincsenek nevezések {selectedYear}-ben.</p>
    {:else}
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Sorozatszám</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Név</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">E-mail</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Kategória</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Eredmény</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Idő</th>
                    </tr>
                </thead>
                <tbody>
                    {#each entries as entry (entry.id)}
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-gray-700">{entry.ticket_number}</td>
                            <td class="px-6 py-4 text-gray-700">{entry.name ?? '—'}</td>
                            <td class="px-6 py-4 text-gray-500">{entry.email ?? '—'}</td>
                            <td class="px-6 py-4 text-gray-500">{entry.category ?? 'Vegyes'}</td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-indigo-600">{entry.score} / {entry.max_score}</span>
                                <span class="text-gray-400 ml-1">({Math.round((entry.score / entry.max_score) * 100)}%)</span>
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-xs">
                                {new Date(entry.created_at).toLocaleString('hu-HU')}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <p class="text-sm text-gray-400 mt-4">{entries.length} nevezés összesen</p>
    {/if}
</main>
