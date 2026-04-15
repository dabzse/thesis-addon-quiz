<script lang="ts">
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { page } from '$app/stores';
    import { authFetch } from '$lib/auth';
    import { fetchQuestionTypes } from '$lib/api';
    import type { QuestionType } from '$lib/types';

    interface Answer {
        id?: number;
        text: string;
        is_correct: boolean;
        correct_position?: number | null;
        match_answer?: string | null;
    }

    const id = $derived($page.params.id);

    let categories: { id: number; name: string }[] = $state([]);
    let questionTypes: QuestionType[] = $state([]);
    let categoryId = $state('');
    let question = $state('');
    let typeId = $state('');
    let answers: Answer[] = $state([]);
    let error = $state('');
    let loading = $state(false);
    let pageLoading = $state(true);

    const selectedType = $derived(questionTypes.find(t => String(t.id) === typeId));

    onMount(async () => {
        try {
            const cats = await fetch('http://localhost:8000/api/categories').then(r => r.json());
            categories = cats;

            const types = await fetchQuestionTypes();
            questionTypes = types;

            const qRes = await authFetch(`http://localhost:8000/api/questions/${id}`);
            const data = await qRes.json();

            categoryId = String(data.category_id);
            question = data.question;

            const matchedType = types.find((t: QuestionType) => t.name === data.type);
            typeId = matchedType ? String(matchedType.id) : '';

            answers = data.answers.map((a: {
                id: number;
                answer: string;
                is_correct: number;
                correct_position: number | null;
                match_answer: string | null;
            }) => ({
                id: a.id,
                text: a.answer,
                is_correct: a.is_correct === 1,
                correct_position: a.correct_position,
                match_answer: a.match_answer,
            })).sort((a: Answer, b: Answer) => (a.correct_position ?? 0) - (b.correct_position ?? 0));
        } catch (e) {
            error = 'Nem sikerült betölteni a kérdést.';
        } finally {
            pageLoading = false;
        }
    });

    function addAnswer() {
        if (selectedType?.name === 'ordering') {
            answers = [...answers, { text: '', is_correct: false, correct_position: answers.length + 1 }];
        } else if (selectedType?.name === 'matching') {
            answers = [...answers, { text: '', is_correct: false, match_answer: '' }];
        } else {
            answers = [...answers, { text: '', is_correct: false }];
        }
    }

    function removeAnswer(index: number) {
        if (answers.length <= 2) return;
        answers = answers.filter((_, i) => i !== index);
        if (selectedType?.name === 'ordering') {
            answers = answers.map((a, i) => ({ ...a, correct_position: i + 1 }));
        }
    }

    function toggleCorrect(index: number) {
        if (selectedType?.name === 'single' || selectedType?.name === 'truefalse') {
            answers = answers.map((a, i) => ({ ...a, is_correct: i === index }));
        } else {
            answers = answers.map((a, i) =>
                i === index ? { ...a, is_correct: !a.is_correct } : a
            );
        }
    }

    async function save() {
        if (!categoryId || !question.trim() || !typeId) {
            error = 'A kategória, a típus és a kérdés megadása kötelező.';
            return;
        }
        if (answers.some(a => !a.text.trim())) {
            error = 'Minden válasz mező kitöltése kötelező.';
            return;
        }
        if (selectedType?.name === 'matching' && answers.some(a => !a.match_answer?.trim())) {
            error = 'Minden párosítás mező kitöltése kötelező.';
            return;
        }
        if (['single', 'multiple', 'truefalse'].includes(selectedType?.name ?? '') && !answers.some(a => a.is_correct)) {
            error = 'Legalább egy helyes választ meg kell jelölni.';
            return;
        }

        loading = true;
        error = '';

        try {
            const res = await authFetch(`http://localhost:8000/api/admin/questions/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    category_id: Number(categoryId),
                    question: question.trim(),
                    type_id: Number(typeId),
                    answers,
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                error = data.error ?? 'Hiba történt.';
                return;
            }

            goto('/admin/questions');
        } catch (e) {
            error = 'Nem sikerült menteni.';
        } finally {
            loading = false;
        }
    }
</script>

<main class="p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="/admin/questions" class="text-indigo-600 hover:underline text-sm">← Vissza</a>
        <h1 class="text-2xl font-bold text-gray-800">Kérdés szerkesztése</h1>
    </div>

    {#if pageLoading}
        <p class="text-gray-400">Betöltés...</p>
    {:else}
        <div class="bg-white rounded-2xl shadow p-8 flex flex-col gap-6">

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Kategória</label>
                <select
                    bind:value={categoryId}
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="">Válassz kategóriát...</option>
                    {#each categories as cat (cat.id)}
                        <option value={String(cat.id)}>{cat.name}</option>
                    {/each}
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Kérdés típusa</label>
                <div class="flex flex-wrap gap-4">
                    {#each questionTypes as qt (qt.id)}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" bind:group={typeId} value={String(qt.id)} class="accent-indigo-600" />
                            <span class="text-sm text-gray-700">{qt.label}</span>
                        </label>
                    {/each}
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">Kérdés szövege</label>
                <textarea
                    bind:value={question}
                    rows="3"
                    placeholder="Írd be a kérdést..."
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"
                ></textarea>
            </div>

            <div class="flex flex-col gap-3">
                <label class="text-sm font-medium text-gray-700">
                    {#if selectedType?.name === 'ordering'}
                        Elemek helyes sorrendben
                    {:else if selectedType?.name === 'matching'}
                        Párok (bal → jobb)
                    {:else}
                        Válaszok
                    {/if}
                </label>

                {#each answers as answer, i (i)}
                    <div class="flex items-center gap-3">

                        {#if selectedType?.name === 'ordering'}
                            <span class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold flex items-center justify-center">
                                {i + 1}
                            </span>
                            <input
                                type="text"
                                bind:value={answer.text}
                                placeholder="Elem {i + 1}"
                                class="flex-1 border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            />

                        {:else if selectedType?.name === 'matching'}
                            <input
                                type="text"
                                bind:value={answer.text}
                                placeholder="Bal oldal"
                                class="flex-1 border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            />
                            <span class="text-gray-400">→</span>
                            <input
                                type="text"
                                bind:value={answer.match_answer}
                                placeholder="Jobb oldal"
                                class="flex-1 border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            />

                        {:else}
                            <button
                                onclick={() => toggleCorrect(i)}
                                title="Helyes válasz"
                                class="shrink-0 w-11 h-6 rounded-full transition-colors relative
                                    {answer.is_correct ? 'bg-indigo-500' : 'bg-gray-200'}"
                            >
                                <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-all top-1
                                    {answer.is_correct ? 'left-6' : 'left-1'}"
                                ></div>
                            </button>
                            <input
                                type="text"
                                bind:value={answer.text}
                                placeholder="Válasz {i + 1}"
                                disabled={selectedType?.name === 'truefalse'}
                                class="flex-1 border rounded-xl px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:bg-gray-50 disabled:text-gray-400"
                            />
                        {/if}

                        {#if selectedType?.name !== 'truefalse' && answers.length > 2}
                            <button
                                onclick={() => removeAnswer(i)}
                                class="text-red-400 hover:text-red-600 text-sm shrink-0"
                            >
                                Törlés
                            </button>
                        {/if}
                    </div>
                {/each}

                {#if selectedType?.name !== 'truefalse'}
                    <button
                        onclick={addAnswer}
                        class="text-indigo-600 hover:underline text-sm text-left mt-1"
                    >
                        + {selectedType?.name === 'matching' ? 'Pár hozzáadása' : selectedType?.name === 'ordering' ? 'Elem hozzáadása' : 'Válasz hozzáadása'}
                    </button>
                {/if}
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
