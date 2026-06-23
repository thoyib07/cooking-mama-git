<x-layout>
    <article class="recipe">
        <h1>{{ $recipe->name }}</h1>
        <img src="{{ $recipe->image_url ?? asset('images/recipe-placeholder.png') }}" alt="{{ $recipe->name }}">
        <h2>Bahan</h2>
        <ul>@foreach ($recipe->ingredients as $i)<li>{{ $i->name }}</li>@endforeach</ul>
        <h2>Langkah</h2>
        <ol>@foreach ($recipe->steps as $step)<li>{{ $step }}</li>@endforeach</ol>
        @livewire('recipe-rating', ['recipe' => $recipe])
    </article>
</x-layout>
