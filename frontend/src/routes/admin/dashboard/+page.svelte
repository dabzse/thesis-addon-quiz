<script lang="ts">
    import { auth } from '$lib/auth';
</script>

<main class="p-8 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Dashboard</h1>
    <p class="text-gray-500 mb-8">Üdv, {$auth.user?.name}!</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a
            href="/admin/questions"
            class="bg-white rounded-2xl shadow p-6 hover:shadow-md hover:bg-indigo-50 transition"
        >
            <h2 class="text-lg font-semibold text-indigo-700 mb-1">Kérdések</h2>
            <p class="text-gray-500 text-sm">Kérdések listázása, szerkesztése, törlése</p>
        </a>

        <a
            href="/admin/questions/new"
            class="bg-indigo-600 rounded-2xl shadow p-6 hover:bg-indigo-700 transition"
        >
            <h2 class="text-lg font-semibold text-white mb-1">+ Új kérdés</h2>
            <p class="text-indigo-200 text-sm">Új kérdés hozzáadása az adatbázishoz</p>
        </a>
    </div>
</main>
