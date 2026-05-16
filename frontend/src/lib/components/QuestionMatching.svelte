<script lang="ts">
    interface Answer {
        id: number;
        answer: string;
        match_answer?: string | null;
    }

    interface Match {
        firstId: string;
        secondId: string;
    }

    interface Props {
        answers: Answer[];
        answered: boolean;
        onMatchChange: (matches: Match[]) => void;
        userMatches: Match[];
    }

    let { answers, answered, onMatchChange, userMatches }: Props = $props();

    let allItems: { id: string; text: string; originalId: number; side: 'left' | 'right' }[] = $state([]);

    const pairColors = [
        'bg-blue-100 border-blue-400 text-blue-800',
        'bg-purple-100 border-purple-400 text-purple-800',
        'bg-orange-100 border-orange-400 text-orange-800',
        'bg-pink-100 border-pink-400 text-pink-800',
        'bg-teal-100 border-teal-400 text-teal-800',
    ];

    let matches: Match[] = $state([]);
    import { onMount } from 'svelte';
    onMount(() => {
        allItems = [
            ...answers.map(a => ({ id: `l-${a.id}`, text: a.answer, originalId: a.id, side: 'left' as const })),
            ...answers.map(a => ({ id: `r-${a.id}`, text: a.match_answer ?? '', originalId: a.id, side: 'right' as const })),
        ].sort(() => Math.random() - 0.5);

        if (userMatches.length > 0) {
            matches = [...userMatches];
        }
    });
    let selected: string | null = $state(null);

    function getPairIndex(itemId: string): number {
        return matches.findIndex(m => m.firstId === itemId || m.secondId === itemId);
    }

    function getItemClass(itemId: string): string {
        if (answered) {
            const pairIdx = getPairIndex(itemId);
            if (pairIdx === -1) return 'bg-white border-gray-200 text-gray-400';
            const match = matches[pairIdx];
            const leftOriginalId = match.firstId.startsWith('l-')
                ? parseInt(match.firstId.slice(2))
                : parseInt(match.secondId.slice(2));
            const rightOriginalId = match.firstId.startsWith('r-')
                ? parseInt(match.firstId.slice(2))
                : parseInt(match.secondId.slice(2));
            const isCorrect = leftOriginalId === rightOriginalId;
            return isCorrect
                ? 'bg-green-100 border-green-400 text-green-800'
                : 'bg-red-100 border-red-400 text-red-800';
        }

        if (selected === itemId) return 'bg-indigo-100 border-indigo-500 text-indigo-800 ring-2 ring-indigo-400';

        const pairIdx = getPairIndex(itemId);
        if (pairIdx !== -1) return pairColors[pairIdx % pairColors.length];

        return 'bg-white border-gray-200 text-gray-700 hover:border-indigo-300';
    }

    function handleClick(itemId: string) {
        if (answered) return;

        if (selected === null) {
            selected = itemId;
            return;
        }

        if (selected === itemId) {
            selected = null;
            return;
        }

        // If either item is already matched, remove the old match
        // Ha már párosítva van valamelyik, töröljük a régi párt
        matches = matches.filter(m => m.firstId !== selected && m.secondId !== selected && m.firstId !== itemId && m.secondId !== itemId);

        // Only match if items are from different sides
        // Csak akkor párosítunk ha különböző oldalról valók
        const selectedSide = selected.startsWith('l-') ? 'left' : 'right';
        const itemSide = itemId.startsWith('l-') ? 'left' : 'right';

        if (selectedSide === itemSide) {
            // Same side — just switch the selection
            // Ugyanaz az oldal — csak átváltjuk a kijelölést
            selected = itemId;
            return;
        }

        matches = [...matches, { firstId: selected, secondId: itemId }];
        selected = null;
        onMatchChange(matches);
    }

    function getAnsweredIcon(itemId: string): string {
        const pairIdx = getPairIndex(itemId);
        if (pairIdx === -1) return '·';
        const match = matches[pairIdx];
        const leftId = match.firstId.startsWith('l-')
            ? parseInt(match.firstId.slice(2))
            : parseInt(match.secondId.slice(2));
        const rightId = match.firstId.startsWith('r-')
            ? parseInt(match.firstId.slice(2))
            : parseInt(match.secondId.slice(2));
        return leftId === rightId ? '✓' : '✗';
    }
</script>

<div class="grid grid-cols-2 gap-3">
    {#each allItems as item (item.id)}
        <button
            onclick={() => handleClick(item.id)}
            disabled={answered}
            class="px-4 py-3 rounded-xl border transition text-left {getItemClass(item.id)}"
        >
            <div class="flex items-center justify-between gap-2">
                <span>{item.text}</span>
                {#if answered}
                    <span>{getAnsweredIcon(item.id)}</span>
                {:else if selected === item.id}
                    <span class="text-indigo-600 text-xs">kijelölve</span>
                {:else if getPairIndex(item.id) !== -1}
                    <span class="text-xs opacity-60">párosítva</span>
                {/if}
            </div>
        </button>
    {/each}
</div>

{#if !answered}
    <p class="text-xs text-gray-400 mt-2">
        {#if selected !== null}
            Válaszd ki a párját!
        {:else}
            Kattints egy elemre a párosításhoz!
        {/if}
    </p>
{/if}
