<script lang="ts">
    import { Config } from '$lib/config';
    import { goto } from '$app/navigation';
    import { resolve } from '$app/paths';
    import { userData } from '$lib/user';

    let ticketNumber = $state('');
    let name = $state('');
    let email = $state('');
    let error = $state('');

    let submitting = $state(false);

    async function start() {
        if (!ticketNumber.trim()) {
            error = 'A belépőjegy sorozatszáma kötelező!';
            return;
        }

        if (submitting) return;
        submitting = true;
        error = '';

        try {
            const year = new Date().getFullYear();
            const res = await fetch(`${Config.API_URL}/tickets/check?ticket=${ticketNumber.trim()}&year=${year}`);
            const data = await res.json();

            if (!res.ok) {
                error = data.error || 'Hiba történt az ellenőrzés során.';
                submitting = false;
                return;
            }

            if (data.used) {
                error = 'Már kitöltötted a kvízt ezzel a jeggyel!';
                submitting = false;
                return;
            }
        } catch {
            error = 'Hiba történt az ellenőrzés során. Próbáld újra!';
            submitting = false;
            return;
        }

        userData.set({
            ticket: ticketNumber.trim(),
            name: name.trim(),
            email: email.trim(),
        });

        submitting = false;
        goto(resolve('/quiz'));
    }
</script>

<main class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-8">
    <div class="bg-white rounded-2xl shadow p-10 w-full max-w-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Kvíz</h1>
        <p class="text-gray-500 text-center mb-8">Add meg adataidat a kezdéshez!</p>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="ticket" class="text-sm font-medium text-gray-700">
                    Belépőjegy sorozatszáma <span class="text-red-500">*</span>
                </label>
                <input
                    id="ticket"
                    type="text"
                    bind:value={ticketNumber}
                    placeholder="pl. 123456"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-medium text-gray-700">
                    Neved <span class="text-gray-400 text-xs">(opcionális)</span>
                </label>
                <input
                    id="name"
                    type="text"
                    bind:value={name}
                    placeholder="pl. Nyilas Mihály"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-gray-700">
                    E-mail cím <span class="text-gray-400 text-xs">(opcionális)</span>
                </label>
                <input
                    id="email"
                    type="email"
                    bind:value={email}
                    placeholder="pl. dabzse@local.host"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
                <p class="text-xs text-gray-400">Ha megadod, elküldjük az eredményedet.</p>
            </div>

            {#if error}
                <p class="text-red-500 text-sm">{error}</p>
            {/if}

            <button
                onclick={start}
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition mt-2"
            >
                Kezdés →
            </button>
        </div>
    </div>
</main>
