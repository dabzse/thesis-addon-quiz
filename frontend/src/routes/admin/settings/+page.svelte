<script lang="ts">
    import { onMount } from 'svelte';
    import { authFetch } from '$lib/auth';

    let questionTimer = $state('0');
    let totalTimer = $state('0');
    let activeYear = $state('2026');
    let showCorrectDuring = $state('1');
    let showCorrectFinal = $state('1');
    let loading = $state(true);
    let saving = $state(false);
    let error = $state('');
    let success = $state(false);

    onMount(async () => {
        try {
            const res = await authFetch('http://localhost:8000/api/settings');
            const data = await res.json();
            questionTimer = data.question_timer ?? '0';
            totalTimer = data.total_timer ?? '0';
            activeYear = data.active_year ?? '2026';
            showCorrectDuring = data.show_correct_during ?? '1';
            showCorrectFinal = data.show_correct_final ?? '1';
        } catch (e) {
            error = 'Nem sikerült betölteni a beállításokat.';
        } finally {
            loading = false;
        }
    });

    async function save() {
        saving = true;
        error = '';
        success = false;

        try {
            const res = await authFetch('http://localhost:8000/api/admin/settings', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    question_timer:       questionTimer,
                    total_timer:          totalTimer,
                    active_year:          activeYear,
                    show_correct_during:  showCorrectDuring,
                    show_correct_final:   showCorrectFinal,
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                error = data.error ?? 'Hiba történt.';
                return;
            }

            success = true;
        } catch (e) {
            error = 'Nem sikerült menteni.';
        } finally {
            saving = false;
        }
    }

    function formatTime(seconds: string): string {
        const s = parseInt(seconds);
        if (s === 0) return 'Kikapcsolva';
        if (s < 60) return `${s} másodperc`;
        const m = Math.floor(s / 60);
        const r = s % 60;
        return r > 0 ? `${m} perc ${r} másodperc` : `${m} perc`;
    }
</script>

<main class="p-8 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-8">Beállítások</h1>

    {#if loading}
        <p class="text-gray-400">Betöltés...</p>
    {:else}
        <div class="bg-white rounded-2xl shadow p-8 flex flex-col gap-8">

            <div class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Időzítők</h2>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700">
                        Kérdésenkénti időkorlát (másodperc, 0 = kikapcsolva)
                    </label>
                    <div class="flex items-center gap-4">
                        <input
                            type="number"
                            bind:value={questionTimer}
                            min="0"
                            max="300"
                            class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-32"
                        />
                        <span class="text-sm text-gray-400">{formatTime(questionTimer)}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700">
                        Teljes kvíz időkorlát (másodperc, 0 = kikapcsolva)
                    </label>
                    <div class="flex items-center gap-4">
                        <input
                            type="number"
                            bind:value={totalTimer}
                            min="0"
                            max="7200"
                            class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-32"
                        />
                        <span class="text-sm text-gray-400">{formatTime(totalTimer)}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Helyes válaszok megjelenítése</h2>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Kérdés után (csak időzítő nélkül)</p>
                        <p class="text-xs text-gray-400">Megerősítés után látszik-e a helyes válasz</p>
                    </div>
                    <button
                        onclick={() => showCorrectDuring = showCorrectDuring === '1' ? '0' : '1'}
                        class="shrink-0 w-11 h-6 rounded-full transition-colors relative
                            {showCorrectDuring === '1' ? 'bg-indigo-500' : 'bg-gray-200'}"
                    >
                        <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-all top-1
                            {showCorrectDuring === '1' ? 'left-6' : 'left-1'}"
                        ></div>
                    </button>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Végső oldalon</p>
                        <p class="text-xs text-gray-400">Az eredmény oldalon látszik-e a helyes válasz</p>
                    </div>
                    <button
                        onclick={() => showCorrectFinal = showCorrectFinal === '1' ? '0' : '1'}
                        class="shrink-0 w-11 h-6 rounded-full transition-colors relative
                            {showCorrectFinal === '1' ? 'bg-indigo-500' : 'bg-gray-200'}"
                    >
                        <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-all top-1
                            {showCorrectFinal === '1' ? 'left-6' : 'left-1'}"
                        ></div>
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Általános</h2>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-gray-700">Aktív év</label>
                    <input
                        type="number"
                        bind:value={activeYear}
                        min="2020"
                        max="2099"
                        class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-32"
                    />
                    <p class="text-xs text-gray-400">Ez az év jelenik meg alapértelmezettként a főoldalon.</p>
                </div>
            </div>

            {#if error}
                <p class="text-red-500 text-sm">{error}</p>
            {/if}
            {#if success}
                <p class="text-green-600 text-sm">✓ Beállítások mentve!</p>
            {/if}

            <button
                onclick={save}
                disabled={saving}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition disabled:opacity-40 self-start"
            >
                {saving ? 'Mentés...' : 'Mentés'}
            </button>
        </div>
    {/if}
</main>
