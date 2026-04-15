<script lang="ts">
    import { onMount } from 'svelte';
    import { authFetch } from '$lib/auth';

    interface Question {
        id: number;
        question: string;
        type: string;
        category: string;
        event_year: number;
    }

    let questions: Question[] = $state([]);
    let loading = $state(true);
    let error = $state('');
    let selectedYear = $state(new Date().getFullYear());

    const typeLabel: Record<string, string> = {
        single:    'Egy helyes',
        multiple:  'Több helyes',
        truefalse: 'Igaz/Hamis',
    };

    async function loadQuestions() {
        loading = true;
        error = '';
        try {
            const res = await authFetch(`http://localhost:8000/api/admin/questions?year=${selectedYear}`);
            questions = await res.json();
        } catch (e) {
            error = 'Nem sikerült betölteni a kérdéseket.';
        } finally {
            loading = false;
        }
    }

    onMount(() => loadQuestions());

    async function deleteQuestion(id: number) {
        if (!confirm('Biztosan törlöd ezt a kérdést?')) return;

        try {
            await authFetch(`http://localhost:8000/api/admin/questions/${id}`, {
                method: 'DELETE',
            });
            questions = questions.filter(q => q.id !== id);
        } catch (e) {
            error = 'Törlés sikertelen.';
        }
    }
</script>

<main class="p-8 max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Kérdések</h1>
        <a
            href="/admin/questions/new"
            class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition text-sm"
        >
            + Új kérdés
        </a>
    </div>

    <div class="flex items-center gap-4 mb-6">
        <label class="text-sm font-medium text-gray-700">Év:</label>
        <input
            type="number"
            bind:value={selectedYear}
            min="2020"
            max="2099"
            class="border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-32"
        />
        <button
            onclick={loadQuestions}
            class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition text-sm"
        >
            Szűrés
        </button>
    </div>

    {#if loading}
        <p class="text-gray-400">Betöltés...</p>
    {:else if error}
        <p class="text-red-500">{error}</p>
    {:else if questions.length === 0}
        <p class="text-gray-400">Nincsenek kérdések {selectedYear}-ban/ben.</p>
    {:else}
        <div class="flex flex-col gap-3">
            {#each questions as q (q.id)}
                <div class="bg-white rounded-xl shadow px-6 py-4 flex justify-between items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-gray-800 font-medium truncate">{q.question}</p>
                        <p class="text-sm text-gray-400 mt-1">
                            {q.category} · {typeLabel[q.type] ?? q.type} · {q.event_year}
                        </p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <a
                            href="/admin/questions/{q.id}/edit"
                            class="text-sm text-indigo-600 hover:underline"
                        >
                            Szerkesztés
                        </a>
                        <button
                            onclick={() => deleteQuestion(q.id)}
                            class="text-sm text-red-500 hover:underline"
                        >
                            Törlés
                        </button>
                    </div>
                </div>
            {/each}
        </div>
    {/if}
</main>
