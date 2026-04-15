<script lang="ts">
    import { goto } from '$app/navigation';
    import { userData } from '$lib/user';

    let ticketNumber = $state('');
    let name = $state('');
    let email = $state('');
    let error = $state('');

    function start() {
        if (!ticketNumber.trim()) {
            error = 'A belépőjegy sorozatszáma kötelező!';
            return;
        }
        error = '';

        userData.set({
            ticket: ticketNumber.trim(),
            name: name.trim(),
            email: email.trim(),
        });

        goto('/quiz');
    }
</script>

<main class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-8">
    <div class="bg-white rounded-2xl shadow p-10 w-full max-w-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Kvíz</h1>
        <p class="text-gray-500 text-center mb-8">Add meg adataidat a kezdéshez!</p>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">
                    Belépőjegy sorozatszáma <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    bind:value={ticketNumber}
                    placeholder="pl. 123456"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">
                    Neved <span class="text-gray-400 text-xs">(opcionális)</span>
                </label>
                <input
                    type="text"
                    bind:value={name}
                    placeholder="pl. Nyilas Mihály"
                    class="border rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-gray-700">
                    E-mail cím <span class="text-gray-400 text-xs">(opcionális)</span>
                </label>
                <input
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
