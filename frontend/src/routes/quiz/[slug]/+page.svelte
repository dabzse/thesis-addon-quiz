<script lang="ts">
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { get } from 'svelte/store';
    import { userData } from '$lib/user';
    import { page } from '$app/stores';
    import { fetchQuestionsByCategory, fetchRandomQuestions, fetchSettings } from '$lib/api';
    import type { Question } from '$lib/types';
    import QuestionChoices from '$lib/components/QuestionChoices.svelte';
    import QuestionOrdering from '$lib/components/QuestionOrdering.svelte';
    import QuestionMatching from '$lib/components/QuestionMatching.svelte';

    const slug = $derived($page.params.slug ?? '');

    let questions: Question[] = $state([]);
    let current = $state(0);
    let selected: number[] = $state([]);
    let answered = $state(false);
    let score = $state(0);
    let finished = $state(false);
    let loading = $state(true);
    let error = $state('');

    // Ordering
    let userOrder: { id: number; answer: string; correct_position: number }[] = $state([]);

    // Matching
    let userMatches: { firstId: string; secondId: string }[] = $state([]);
    let matchIsCorrect = $state(false);

    // Eredmény
    let answeredQuestions: {
        question: string;
        type: Question['type'];
        answers: Question['answers'];
        selected: number[];
        userOrder: { id: number; answer: string; correct_position: number }[];
        userMatches: { firstId: string; secondId: string }[];
    }[] = $state([]);

    // Beküldés
    let entryError = $state('');
    let entrySuccess = $state(false);
    let entrySubmitted = $state(false);

    // Timer
    let questionTimerMax = $state(0);
    let totalTimerMax = $state(0);
    let questionTimeLeft = $state(0);
    let totalTimeLeft = $state(0);
    let questionInterval: ReturnType<typeof setInterval> | null = null;
    let totalInterval: ReturnType<typeof setInterval> | null = null;

    // Settings
    let showCorrectDuring = $state(true);
    let showCorrectFinal = $state(true);

    onMount(async () => {
        const user = get(userData);
        if (!user.ticket) {
            goto('/');
            return;
        }
        questions = [];
        current = 0;
        selected = [];
        userOrder = [];
        userMatches = [];
        answered = false;
        score = 0;
        finished = false;

        try {
            if (slug === 'random') {
                const data = await fetchRandomQuestions();
                questions = data.questions;
            } else {
                const data = await fetchQuestionsByCategory(slug);
                questions = data.questions;
            }

            questions = questions.map(q => {
                if (q.type === 'ordering') {
                    return {
                        ...q,
                        answers: [...q.answers].sort(() => Math.random() - 0.5)
                    };
                }
                return q;
            });

            if (questions.length === 0) {
                error = 'Nincs kérdés ebben a kategóriában.';
            }
        } catch (e) {
            error = 'Nem sikerült betölteni a kérdéseket.';
        } finally {
            loading = false;
        }

        const settings = await fetchSettings();
        questionTimerMax = parseInt(settings.question_timer ?? '0');
        totalTimerMax = parseInt(settings.total_timer ?? '0');
        showCorrectDuring = settings.show_correct_during !== '0';
        showCorrectFinal = settings.show_correct_final !== '0';

        if (totalTimerMax > 0) {
            totalTimeLeft = totalTimerMax;
            totalInterval = setInterval(() => {
                totalTimeLeft--;
                if (totalTimeLeft <= 0) {
                    clearInterval(totalInterval!);
                    if (questionInterval) clearInterval(questionInterval);
                    // Ha van aktuális kérdés ami még nem lett megválaszolva, mentsük el
                    if (!answered && questions[current]) {
                        answeredQuestions = [...answeredQuestions, {
                            question: questions[current].question,
                            type: questions[current].type,
                            answers: questions[current].answers,
                            selected: [],
                            userOrder: [],
                            userMatches: [],
                        }];
                    }
                    finished = true;
                }
            }, 1000);
        }

        if (questionTimerMax > 0) {
            startQuestionTimer();
        }
    });

    function toggleAnswer(answerId: number) {
        if (answered) return;
        if (selected.includes(answerId)) {
            selected = selected.filter(a => a !== answerId);
        } else {
            selected = [...selected, answerId];
        }
    }

    function handleOrderChange(items: { id: number; answer: string; correct_position: number }[]) {
        userOrder = items;
    }

    function handleMatchChange(matches: { firstId: string; secondId: string }[], isCorrect: boolean) {
        userMatches = matches;
        matchIsCorrect = isCorrect;
    }

    function canConfirm(): boolean {
        const q = questions[current];
        if (!q) return false;
        if (q.type === 'ordering') return true;
        if (q.type === 'matching') return userMatches.length === q.answers.length;
        return selected.length > 0;
    }

    function evaluateCurrent(): boolean {
        const q = questions[current];
        if (q.type === 'ordering') {
            const order = userOrder.length > 0 ? userOrder : q.answers;
            return order.every((item, index) => item.correct_position === index + 1);
        } else if (q.type === 'matching') {
            return matchIsCorrect;
        } else {
            const correctIds = q.answers
                .filter(a => a.is_correct === 1)
                .map(a => a.id);
            return correctIds.length === selected.length &&
                correctIds.every(id => selected.includes(id));
        }
    }

    function confirm() {
        if (questionInterval) clearInterval(questionInterval);
        if (!canConfirm() || answered) return;

        answered = true;

        const isCorrect = evaluateCurrent();
        if (isCorrect) score++;

        const q = questions[current];
        answeredQuestions = [...answeredQuestions, {
            question: q.question,
            type: q.type,
            answers: q.answers,
            selected: [...selected],
            userOrder: [...userOrder],
            userMatches: [...userMatches],
        }];

        // Ha van kérdés timer → automatikusan lép
        if (questionTimerMax > 0) {
            next();
        }
        // Ha nincs timer és show_correct_during ki van kapcsolva → azonnal lép
        else if (!showCorrectDuring) {
            next();
        }
        // Ha nincs timer és show_correct_during be van kapcsolva → megjelenik a Következő gomb
    }

    function next() {
        if (current + 1 >= questions.length) {
            if (totalInterval) clearInterval(totalInterval);
            finished = true;
            return;
        }
        current++;
        selected = [];
        userOrder = [];
        userMatches = [];
        matchIsCorrect = false;
        answered = false;
        startQuestionTimer();
    }

    async function submitEntry() {
        const user = get(userData);
        entryError = '';

        try {
            await fetch('http://localhost:8000/api/entries', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ticket_number: user.ticket,
                    name:          user.name || null,
                    email:         user.email || null,
                    score,
                    max_score:     questions.length,
                    event_year:    new Date().getFullYear(),
                    category_slug: slug === 'random' ? null : slug,
                    questions:     answeredQuestions,
                }),
            });
            entrySuccess = true;
            entrySubmitted = true;
        } catch (e) {
            entryError = 'Hiba történt, próbáld újra!';
        }
    }

    function startQuestionTimer() {
        if (questionInterval) clearInterval(questionInterval);
        if (questionTimerMax === 0) return;

        questionTimeLeft = questionTimerMax;
        questionInterval = setInterval(() => {
            questionTimeLeft--;
            if (questionTimeLeft <= 0) {
                clearInterval(questionInterval!);
                if (!answered) {
                    answered = true;
                    answeredQuestions = [...answeredQuestions, {
                        question: questions[current].question,
                        type: questions[current].type,
                        answers: questions[current].answers,
                        selected: [],
                        userOrder: [],
                        userMatches: [],
                    }];
                    next();
                }
            }
        }, 1000);
    }
</script>

<main class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-8">
    {#if loading}
        <p class="text-gray-400">Betöltés...</p>

    {:else if error}
        <p class="text-red-500">{error}</p>

    {:else if finished}
        <div class="bg-white rounded-2xl shadow p-10 max-w-xl w-full">
            <h2 class="text-3xl font-bold text-gray-800 mb-2 text-center">Vége! 🎉</h2>
            <p class="text-xl text-gray-600 text-center mb-2">
                Eredmény: <span class="font-bold text-indigo-600">{score} / {questions.length}</span>
            </p>
            <p class="text-center text-gray-400 mb-8">
                {Math.round((score / questions.length) * 100)}%
            </p>

            {#if showCorrectFinal}
                <div class="flex flex-col gap-4 mb-8">
                    {#each answeredQuestions as q, i (i)}
                        <div class="border rounded-xl p-4">
                            <p class="font-semibold text-gray-700 mb-2">{i + 1}. {q.question}</p>
                            {#if q.type === 'ordering'}
                                <div class="flex flex-col gap-1 mt-2">
                                    {#each q.userOrder as item, idx (item.id)}
                                        <div class="flex items-center gap-2 text-sm py-1
                                            {item.correct_position === idx + 1 ? 'text-green-700' : 'text-red-600'}"
                                        >
                                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                                {item.correct_position === idx + 1 ? 'bg-green-200' : 'bg-red-200'}"
                                            >{idx + 1}</span>
                                            <span>{item.answer}</span>
                                            <span>{item.correct_position === idx + 1 ? '✓' : `(helyes: ${item.correct_position})`}</span>
                                        </div>
                                    {/each}
                                </div>
                            {:else if q.type === 'matching'}
                                <div class="flex flex-col gap-1 mt-2">
                                    {#each q.userMatches as match (match.firstId)}
                                        {@const leftId = match.firstId.startsWith('l-') ? parseInt(match.firstId.slice(2)) : parseInt(match.secondId.slice(2))}
                                        {@const rightId = match.firstId.startsWith('r-') ? parseInt(match.firstId.slice(2)) : parseInt(match.secondId.slice(2))}
                                        {@const leftAnswer = q.answers.find(a => a.id === leftId)}
                                        {@const rightAnswer = q.answers.find(a => a.id === rightId)}
                                        {@const correct = leftId === rightId}
                                        <div class="flex items-center gap-2 text-sm py-1
                                            {correct ? 'text-green-700' : 'text-red-600'}"
                                        >
                                            <span>{correct ? '✓' : '✗'}</span>
                                            <span>{leftAnswer?.answer}</span>
                                            <span>→</span>
                                            <span>{rightAnswer?.match_answer ?? rightAnswer?.answer}</span>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                {#each q.answers as answer (answer.id)}
                                    <div class="flex items-center gap-2 text-sm py-1
                                        {answer.is_correct
                                            ? 'text-green-700 font-medium'
                                            : q.selected.includes(answer.id)
                                                ? 'text-red-600'
                                                : 'text-gray-400'
                                        }"
                                    >
                                        <span>{answer.is_correct ? '✓' : q.selected.includes(answer.id) ? '✗' : '·'}</span>
                                        <span>{answer.answer}</span>
                                    </div>
                                {/each}
                            {/if}
                        </div>
                    {/each}
                </div>
            {/if}

            <div class="border-t pt-6 flex flex-col gap-4">
                {#if entryError}
                    <p class="text-red-500 text-sm">{entryError}</p>
                {/if}
                {#if entrySuccess}
                    <p class="text-green-600 text-sm">✓ Sikeresen beküldve! Várunk vissza legközelebb is!</p>
                {/if}

                <button
                    onclick={submitEntry}
                    disabled={entrySubmitted}
                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40"
                >
                    {entrySubmitted ? 'Beküldve' : 'Beküldés'}
                </button>

                <a
                    href="/"
                    class="text-center text-indigo-600 hover:underline text-sm"
                >
                    Vissza a főoldalra
                </a>
            </div>
        </div>

    {:else}
        <div class="w-full max-w-xl flex justify-between mb-2">
            {#if questionTimerMax > 0}
                <span class="text-sm font-medium
                    {questionTimeLeft <= 5 ? 'text-red-500' : 'text-gray-500'}">
                    ⏱ {questionTimeLeft}s
                </span>
            {:else}
                <span></span>
            {/if}

            {#if totalTimerMax > 0}
                <span class="text-sm font-medium
                    {totalTimeLeft <= 30 ? 'text-red-500' : 'text-gray-500'}">
                    ⏰ {Math.floor(totalTimeLeft / 60)}:{String(totalTimeLeft % 60).padStart(2, '0')}
                </span>
            {/if}
        </div>

        <div class="w-full max-w-xl mb-4">
            <div class="flex justify-between text-sm text-gray-400 mb-1">
                <span>{current + 1} / {questions.length}</span>
                <span>{Math.round(((current + 1) / questions.length) * 100)}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                    class="bg-indigo-500 h-2 rounded-full transition-all duration-500"
                    style="width: {((current + 1) / questions.length) * 100}%"
                ></div>
            </div>
        </div>

        {#if slug === 'random'}
            <p class="text-sm text-indigo-500 font-medium mb-2">{questions[current].category}</p>
        {/if}

        <div class="bg-white rounded-2xl shadow p-8 max-w-xl w-full">
            <h2 class="text-xl font-semibold text-gray-800 mb-6 whitespace-pre-line">
                {questions[current].question}
            </h2>

            {#if questions[current].type === 'ordering'}
                {#key current}
                    <QuestionOrdering
                        answers={questions[current].answers}
                        {answered}
                        {userOrder}
                        onOrderChange={handleOrderChange}
                    />
                {/key}
            {:else if questions[current].type === 'matching'}
                {#key current}
                    <QuestionMatching
                        answers={questions[current].answers}
                        {answered}
                        {userMatches}
                        onMatchChange={handleMatchChange}
                    />
                {/key}
            {:else}
                <QuestionChoices
                    answers={questions[current].answers}
                    {selected}
                    {answered}
                    onToggle={toggleAnswer}
                    showCorrect={answered && showCorrectDuring && questionTimerMax === 0}
                />
            {/if}

            <div class="mt-6 flex justify-end">
                {#if !answered}
                    <button
                        onclick={confirm}
                        disabled={!canConfirm()}
                        class="bg-indigo-600 text-white px-6 py-2 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40"
                    >
                        Megerősít
                    </button>
                {:else if questionTimerMax === 0 && showCorrectDuring}
                    <button
                        onclick={next}
                        class="bg-indigo-600 text-white px-6 py-2 rounded-xl hover:bg-indigo-700 transition"
                    >
                        {current + 1 >= questions.length ? 'Eredmény' : 'Következő'}
                    </button>
                {/if}
            </div>
        </div>
    {/if}
</main>
