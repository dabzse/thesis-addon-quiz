<script lang="ts">
    import { Config } from '$lib/config';
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { resolve } from '$app/paths';
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
    let submitting = $state(false);

    let entryId: number | null = $state(null);

    // Ordering
    let userOrder: { id: number; answer: string; correct_position: number }[] = $state([]);

    // Matching
    let userMatches: { firstId: string; secondId: string }[] = $state([]);

    // Results / Eredmény
    let answeredQuestions: {
        id: number;
        question: string;
        type: Question['type'];
        answers: Question['answers'];
        selected: number[];
        userOrder: { id: number; answer: string; correct_position: number }[];
        userMatches: { firstId: string; secondId: string }[];
        isCorrect?: boolean;
    }[] = $state([]);

    // Submission / Beküldés
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
    let showUnansweredFinal = $state(true);

    // Modal
    let showEmailModal = $state(false);
    let modalEmail = $state('');
    let modalName = $state('');
    let sendingEmail = $state(false);
    let emailSentStatus: 'success' | 'error' | null = $state(null);
    let emailWasSentSuccessfully = $state(false);

    onMount(async () => {
        const user = get(userData);
        if (!user.ticket) {
            goto(`${resolve('/')}`);
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
        } catch {
            error = 'Nem sikerült betölteni a kérdéseket.';
        } finally {
            loading = false;
        }

        try {
            const settings = await fetchSettings();
            questionTimerMax = parseInt(settings.question_timer ?? '0');
            totalTimerMax = parseInt(settings.total_timer ?? '0');
            showCorrectDuring = settings.show_correct_during !== '0';
            showCorrectFinal = settings.show_correct_final !== '0';
            showUnansweredFinal = settings.show_unanswered_final !== '0';
        } catch {
            // If settings fail to load, continue with defaults
            // Ha nem sikerül betölteni a beállításokat, az alapértékekkel megyünk
        }

        if (totalTimerMax > 0) {
            totalTimeLeft = totalTimerMax;
            totalInterval = setInterval(() => {
                totalTimeLeft--;
                if (totalTimeLeft <= 0) {
                    clearInterval(totalInterval!);
                    if (questionInterval) clearInterval(questionInterval);
                    let finalAnswers = [...answeredQuestions];
                    for (let i = current; i < questions.length; i++) {
                        if (i === current && answered) continue;
                        finalAnswers.push({
                            id: questions[i].id,
                            question: questions[i].question,
                            type: questions[i].type,
                            answers: questions[i].answers,
                            selected: [],
                            userOrder: [],
                            userMatches: [],
                        });
                    }
                    answeredQuestions = finalAnswers;
                    submitEntry();
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

    function handleMatchChange(matches: { firstId: string; secondId: string }[]) {
        userMatches = matches;

    }

    function canConfirm(): boolean {
        const q = questions[current];
        if (!q) return false;
        if (q.type === 'ordering') return true;
        if (q.type === 'matching') return userMatches.length === q.answers.length;
        return selected.length > 0;
    }

    function confirm() {
        if (questionInterval) clearInterval(questionInterval);
        if (!canConfirm() || answered) return;

        answered = true;

        const q = questions[current];
        answeredQuestions = [...answeredQuestions, {
            id: q.id,
            question: q.question,
            type: q.type,
            answers: q.answers,
            selected: [...selected],
            userOrder: [...userOrder],
            userMatches: [...userMatches],
        }];

        // If correct answers shouldn't be shown, or there's a timer, advance immediately
        // Ha nem kell megmutatni a helyes választ, vagy van időzítő, azonnal lépünk
        if (!showCorrectDuring || questionTimerMax > 0) {
            next();
        }
        // Otherwise the "Next" button handles advancing
        // Különben a "Következő" gomb kezeli a továbblépést
    }

    function next() {
        if (current + 1 >= questions.length) {
            if (totalInterval) clearInterval(totalInterval);
            submitEntry();
            return;
        }
        current++;
        selected = [];
        userOrder = [];
        userMatches = [];

        answered = false;
        startQuestionTimer();
    }

    async function submitEntry() {
        if (submitting || entrySubmitted) return;
        submitting = true;
        const user = get(userData);
        entryError = '';

        try {
            const res = await fetch(`${Config.API_URL}/entries`, {
                method: 'POST',
                headers: Config.APP_JSON,
                body: JSON.stringify({
                    ticket_number: user.ticket,
                    name:          user.name || null,
                    email:         user.email || null,
                    event_year:    new Date().getFullYear(),
                    category_slug: slug === 'random' ? null : slug,
                    questions:     answeredQuestions.map(q => ({
                        id: q.id,
                        question: q.question,
                        type: q.type,
                        answers: q.answers,
                        selected: q.selected,
                        userOrder: q.userOrder,
                        userMatches: q.userMatches
                    })),
                }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Hiba történt, próbáld újra!');

            score = data.score;
            entryId = data.id;
            answeredQuestions = data.questions;

            entrySuccess = true;
            entrySubmitted = true;
            if (user.email) {
                emailWasSentSuccessfully = true;
            }
            submitting = false;
            finished = true;
        } catch (e) {
            entryError = (e as Error).message || 'Hiba történt, próbáld újra!';
            submitting = false;
        }
    }

    async function sendEmailOnly() {
        if (sendingEmail || !modalEmail.trim()) return;
        sendingEmail = true;
        emailSentStatus = null;

        try {
            const res = await fetch(`${Config.API_URL}/entries/email`, {
                method: 'POST',
                headers: Config.APP_JSON,
                body: JSON.stringify({
                    entry_id: entryId,
                    email: modalEmail.trim(),
                    name: modalName.trim(),
                    score,
                    maxScore: questions.length,
                    questions: answeredQuestions
                })
            });

            if (!res.ok) throw new Error();
            emailSentStatus = 'success';
            emailWasSentSuccessfully = true;
            setTimeout(() => {
                showEmailModal = false;
                emailSentStatus = null;
            }, 2000);
        } catch {
            emailSentStatus = 'error';
        } finally {
            sendingEmail = false;
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
                        id: questions[current].id,
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

    {:else if submitting}
        <div class="flex flex-col items-center justify-center">
            <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-indigo-600 font-medium mt-4">Eredmények kiértékelése és beküldése...</p>
        </div>

    {:else if error}
        <p class="text-red-500">{error}</p>

    {:else if !finished && entryError}
        <div class="bg-white rounded-2xl shadow p-10 max-w-xl w-full text-center">
            <p class="text-red-500 mb-6 font-medium text-lg">{entryError}</p>
            <button
                onclick={submitEntry}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition"
            >
                Újrapróbálkozás
            </button>
            <br/><br/>
            <a href="{resolve('/')}" class="text-indigo-600 hover:underline">Vissza a főoldalra</a>
        </div>

    {:else if finished}
        <div class="bg-white rounded-2xl shadow p-10 max-w-xl w-full">
            <h2 class="text-3xl font-bold text-gray-800 mb-2 text-center">Vége! 🎉</h2>
            <p class="text-xl text-gray-600 text-center mb-2">
                Eredmény: <span class="font-bold text-indigo-600">{score} / {questions.length}</span>
            </p>
            <p class="text-center text-gray-400 mb-8">
                {questions.length > 0 ? Math.round((score / questions.length) * 100) : 0}%
            </p>

            {#if showCorrectFinal}
                <div class="flex flex-col gap-4 mb-8">
                    {#each answeredQuestions as q, i (i)}
                        {@const isAnswered = (q.selected && q.selected.length > 0) || (q.userOrder && q.userOrder.length > 0) || (q.userMatches && q.userMatches.length > 0)}
                        {#if showUnansweredFinal || isAnswered}
                            <div class="border rounded-xl p-4">
                                <p class="font-semibold text-gray-700 mb-2">{i + 1}. {q.question}</p>
                            {#if q.type === 'ordering'}
                                <div class="flex flex-col gap-1 mt-2">
                                    {#if q.userOrder.length > 0}
                                        {#each q.userOrder as item, idx (item.id)}
                                            {@const trueAnswer = q.answers.find(a => a.id == item.id)}
                                            <div class="flex items-center gap-2 text-sm py-1
                                                {trueAnswer?.correct_position == idx + 1 ? 'text-green-700' : 'text-red-600'}"
                                            >
                                                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                                    {trueAnswer?.correct_position == idx + 1 ? 'bg-green-200' : 'bg-red-200'}"
                                                >{idx + 1}</span>
                                                <span>{item.answer}</span>
                                                <span>{trueAnswer?.correct_position == idx + 1 ? '✓' : `(helyes: ${trueAnswer?.correct_position})`}</span>
                                            </div>
                                        {/each}
                                    {:else}
                                        <span class="text-red-500 italic text-sm">Nem válaszoltad meg.</span>
                                    {/if}
                                    {#if !q.isCorrect}
                                        <div class="mt-2 text-xs text-gray-500">Helyes sorrend:</div>
                                        {#each [...q.answers].sort((a,b) => (a.correct_position || 0) - (b.correct_position || 0)) as ans (ans.id)}
                                            <div class="text-xs text-green-700">{ans.correct_position}. {ans.answer}</div>
                                        {/each}
                                    {/if}
                                </div>
                            {:else if q.type === 'matching'}
                                <div class="flex flex-col gap-1 mt-2">
                                    {#if q.userMatches && q.userMatches.length > 0}
                                        {#each q.userMatches as match (match.firstId)}
                                            {@const leftId = match.firstId.startsWith('l-') ? parseInt(match.firstId.slice(2)) : parseInt(match.secondId.slice(2))}
                                            {@const rightId = match.firstId.startsWith('r-') ? parseInt(match.firstId.slice(2)) : parseInt(match.secondId.slice(2))}
                                            {@const leftAnswer = q.answers.find(a => a.id == leftId)}
                                            {@const rightAnswer = q.answers.find(a => a.id == rightId)}
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
                                    {:else}
                                        <span class="text-red-500 italic text-sm">Nem válaszoltad meg.</span>
                                    {/if}
                                    {#if !q.isCorrect}
                                        <div class="mt-2 text-xs text-gray-500">Helyes párok:</div>
                                        {#each q.answers as ans (ans.id)}
                                            <div class="text-xs text-green-700">{ans.answer} → {ans.match_answer ?? ans.answer}</div>
                                        {/each}
                                    {/if}
                                </div>
                            {:else}
                                <div class="flex flex-col gap-1 mt-2">
                                    {#if q.selected.length === 0}
                                        <span class="text-red-500 italic text-sm mb-1">Nem válaszoltad meg.</span>
                                    {/if}
                                    {#each q.answers as answer (answer.id)}
                                        <div class="flex items-center gap-2 text-sm py-1
                                            {answer.is_correct == 1
                                                ? 'text-green-700 font-medium'
                                                : q.selected.some(sid => sid == answer.id)
                                                    ? 'text-red-600'
                                                    : 'text-gray-400'
                                            }"
                                        >
                                            <span>{answer.is_correct == 1 ? '✓' : q.selected.some(sid => sid == answer.id) ? '✗' : '·'}</span>
                                            <span>{answer.answer}</span>
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                        {/if}
                    {/each}
                </div>
            {/if}

            <div class="border-t pt-6 flex flex-col gap-4">
                {#if entrySuccess}
                    <p class="text-green-600 text-sm">✓ Sikeresen beküldve! Várunk vissza legközelebb is!</p>
                {/if}

                <button
                    onclick={() => {
                        const user = get(userData);
                        modalEmail = user.email || '';
                        modalName = user.name || '';
                        showEmailModal = true;
                    }}
                    disabled={emailWasSentSuccessfully}
                    class="bg-indigo-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors shadow-lg shadow-indigo-200"
                >
                    {emailWasSentSuccessfully ? 'Már elküldted a kiértékelést' : 'Eredmények elküldése e-mailben'}
                </button>

                <a
                    href="{resolve('/')}"
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

    {#if showEmailModal}
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 animate-in fade-in zoom-in duration-300">
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Eredmények elküldése</h3>
                <p class="text-gray-600 mb-6">Amennyiben megadod az adataidat, elküldjük az eredményeket!</p>

                <div class="flex flex-col gap-4">
                    <div>
                        <label for="modal-name" class="block text-sm font-medium text-gray-700 mb-1">Név (opcionális)</label>
                        <input
                            id="modal-name"
                            type="text"
                            bind:value={modalName}
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="Neved"
                        />
                    </div>

                    <div>
                        <label for="modal-email" class="block text-sm font-medium text-gray-700 mb-1">E-mail cím</label>
                        <input
                            id="modal-email"
                            type="email"
                            bind:value={modalEmail}
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                            placeholder="[EMAIL_ADDRESS]"
                        />
                    </div>

                    {#if emailSentStatus === 'success'}
                        <p class="text-green-600 text-center font-medium">✓ Sikeresen elküldve!</p>
                    {:else if emailSentStatus === 'error'}
                        <p class="text-red-600 text-center font-medium">✗ Hiba történt a küldés során.</p>
                    {/if}

                    <div class="flex gap-3 mt-4">
                        <button
                            onclick={() => showEmailModal = false}
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Mégsem
                        </button>
                        <button
                            onclick={sendEmailOnly}
                            disabled={!modalEmail.trim() || sendingEmail}
                            class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-md shadow-indigo-200"
                        >
                            {sendingEmail ? 'Küldés...' : 'Elküldés'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</main>
