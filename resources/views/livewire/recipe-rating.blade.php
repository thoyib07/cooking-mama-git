<div class="rating">
    <div class="rating__stars">
        @for ($i = 1; $i <= 5; $i++)
            <button wire:click="rate({{ $i }})" @disabled($hasRated) aria-label="Beri {{ $i }} bintang">★</button>
        @endfor
    </div>
    <p>{{ $average }} / 5 ({{ $count }} penilaian)</p>
    @if ($hasRated)<p class="rating__thanks">Terima kasih atas penilaiannya!</p>@endif
</div>
