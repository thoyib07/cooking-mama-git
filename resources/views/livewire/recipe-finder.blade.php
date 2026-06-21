<div class="finder">
    <form wire:submit.prevent="addIngredient" class="finder__add">
        <input type="text" wire:model="newIngredient" placeholder="Tambah bahan..." aria-label="Bahan">
        <button type="submit">Tambah</button>
    </form>

    <ul class="finder__chips">
        @foreach ($ingredients as $i => $ing)
            <li>{{ $ing }} <button wire:click="removeIngredient({{ $i }})" aria-label="Hapus">×</button></li>
        @endforeach
    </ul>

    <button wire:click="search" class="finder__search" @disabled(empty($ingredients))>Cari Resep</button>

    <div class="ai">
        <button wire:click="exploreWithAi" wire:loading.attr="disabled" @disabled(empty($ingredients))>
            <span wire:loading.remove wire:target="exploreWithAi">Eksplor dengan AI</span>
            <span wire:loading wire:target="exploreWithAi">Mencari ide resep...</span>
        </button>
        @if ($aiError)<p class="ai__error" role="alert">{{ $aiError }}</p>@endif
    </div>

    @if ($searched)
        <section class="results">
            @forelse ($results as $r)
                <article class="result">
                    <a href="{{ route('recipes.show', $r['id']) }}">{{ $r['name'] }}</a>
                    <span class="result__score">{{ (int) round($r['score'] * 100) }}% cocok</span>
                    @if (!empty($r['missing']))
                        <p class="result__missing">Kurang: {{ implode(', ', $r['missing']) }}</p>
                    @endif
                </article>
            @empty
                <p>Tidak ada resep yang cukup cocok di database.</p>
            @endforelse
        </section>
    @endif
</div>
