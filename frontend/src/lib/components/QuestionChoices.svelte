<script lang="ts">
    interface Answer {
        id: number;
        answer: string;
        is_correct: number;
    }

    interface Props {
        answers: Answer[];
        selected: number[];
        answered: boolean;
        onToggle: (id: number) => void;
        showCorrect?: boolean;
    }

    let { answers, selected, answered, onToggle, showCorrect = true }: Props = $props();

    function getButtonClass(answer: Answer): string {
        if (!answered) {
            return selected.includes(answer.id)
                ? 'bg-indigo-50 border-indigo-400 text-indigo-800'
                : 'bg-white border-gray-200 text-gray-700 hover:border-indigo-300';
        }

        if (!showCorrect) {
            // Nem mutatjuk a helyes választ — csak a kijelöltet jelezzük semlegesen
            return selected.includes(answer.id)
                ? 'bg-gray-100 border-gray-400 text-gray-700'
                : 'bg-white border-gray-200 text-gray-400';
        }

        // showCorrect = true
        if (answer.is_correct) return 'bg-green-100 border-green-400 text-green-800';
        if (selected.includes(answer.id)) return 'bg-red-100 border-red-400 text-red-800';
        return 'bg-white border-gray-200 text-gray-400';
    }

    function getSwitchClass(answer: Answer): string {
        if (!answered) {
            return selected.includes(answer.id) ? 'bg-indigo-500' : 'bg-gray-200';
        }

        if (!showCorrect) {
            return selected.includes(answer.id) ? 'bg-gray-400' : 'bg-gray-200';
        }

        if (answer.is_correct) return 'bg-green-400';
        if (selected.includes(answer.id)) return 'bg-red-400';
        return 'bg-gray-200';
    }
</script>

<div class="flex flex-col gap-3">
    {#each answers as answer (answer.id)}
        <button
            onclick={() => onToggle(answer.id)}
            disabled={answered}
            class="flex items-center justify-between px-4 py-3 rounded-xl border transition w-full {getButtonClass(answer)}"
        >
            <span>{answer.answer}</span>

            <div class="relative inline-flex items-center ml-4 shrink-0">
                <div class="w-11 h-6 rounded-full transition {getSwitchClass(answer)}"></div>
                <div class="absolute w-4 h-4 bg-white rounded-full shadow transition-all
                    {selected.includes(answer.id) ? 'left-6' : 'left-1'}"
                ></div>
            </div>
        </button>
    {/each}
</div>
