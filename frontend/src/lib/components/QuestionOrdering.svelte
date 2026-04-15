<script lang="ts">
    import { dndzone } from 'svelte-dnd-action';
    import { flip } from 'svelte/animate';
    import { onMount } from 'svelte';

    interface Answer {
        id: number;
        answer: string;
        correct_position?: number | null;
    }

    interface DndItem {
        id: number;
        answer: string;
        correct_position: number;
    }

    interface Props {
        answers: Answer[];
        answered: boolean;
        onOrderChange: (items: DndItem[]) => void;
        userOrder: DndItem[];
    }

    let { answers, answered, onOrderChange, userOrder }: Props = $props();

    let items: DndItem[] = $state(
        userOrder.length > 0
            ? userOrder
            : answers.map(a => ({ id: a.id, answer: a.answer, correct_position: a.correct_position ?? 0 }))
    );

    onMount(() => {
        onOrderChange([...items]);
    });

    function handleDndConsider(e: CustomEvent<{ items: DndItem[] }>) {
        items = e.detail.items;
    }

    function handleDndFinalize(e: CustomEvent<{ items: DndItem[] }>) {
        items = e.detail.items;
        onOrderChange(items);
    }

    function isCorrect(item: DndItem, index: number): boolean {
        return item.correct_position === index + 1;
    }
</script>

<div
    use:dndzone={{ items, dragDisabled: answered }}
    onconsider={handleDndConsider}
    onfinalize={handleDndFinalize}
    class="flex flex-col gap-3"
>
    {#each items as item, i (item.id)}
        <div
            animate:flip={{ duration: 200 }}
            class="flex items-center gap-3 px-4 py-3 rounded-xl border transition
                {answered
                    ? isCorrect(item, i)
                        ? 'bg-green-100 border-green-400 text-green-800'
                        : 'bg-red-100 border-red-400 text-red-800'
                    : 'bg-white border-gray-200 text-gray-700 cursor-grab active:cursor-grabbing'
                }"
        >
            <span class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold
                {answered
                    ? isCorrect(item, i)
                        ? 'bg-green-200 text-green-800'
                        : 'bg-red-200 text-red-800'
                    : 'bg-indigo-100 text-indigo-700'
                }"
            >
                {i + 1}
            </span>
            <span class="flex-1">{item.answer}</span>

            {#if !answered}
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                </svg>
            {:else}
                <span class="shrink-0 text-lg">
                    {isCorrect(item, i) ? '✓' : '✗'}
                </span>
            {/if}
        </div>
    {/each}
</div>

{#if !answered}
    <p class="text-xs text-gray-400 mt-2">Húzd a megfelelő sorrendbe!</p>
{/if}
