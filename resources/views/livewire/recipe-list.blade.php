<div class="space-y-4">
    <input
        type="text"
        wire:model.live.debounce.400ms="search"
        placeholder="Cari nama resep..."
        aria-label="Cari resep"
        class="w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-600 placeholder-stone-400"
    >

    <section class="space-y-3">
        @forelse ($recipes as $recipe)
            <a wire:navigate href="{{ route('recipes.show', $recipe->id) }}" class="block rounded-xl bg-white p-4 shadow-sm active:shadow-none">
                <span class="font-semibold text-stone-800">{{ $recipe->name }}</span>
                @if ($recipe->servings)
                    <span class="ml-2 text-xs text-stone-400">🍽️ {{ $recipe->servings }} porsi</span>
                @endif
            </a>
        @empty
            <p class="rounded-xl bg-white p-4 text-center text-sm text-stone-400 shadow-sm">
                Tidak ada resep yang cocok.
            </p>
        @endforelse
    </section>

    @if ($hasMore)
        <button
            type="button"
            wire:click="loadMore"
            wire:loading.attr="disabled"
            wire:target="loadMore"
            class="w-full rounded-xl border border-stone-200 bg-white py-3 text-sm font-semibold text-stone-600 active:bg-stone-50 disabled:opacity-40"
        >
            <span wire:loading.remove wire:target="loadMore">Muat lebih banyak</span>
            <span wire:loading wire:target="loadMore">Memuat...</span>
        </button>
    @endif
</div>
