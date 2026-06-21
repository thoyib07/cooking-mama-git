<x-layout>
    <article class="recipe">
        <h1>{{ $recipe->name }}</h1>
        <img src="{{ $recipe->image_url ?? asset('images/recipe-placeholder.png') }}" alt="{{ $recipe->name }}">
        <h2>Bahan</h2>
        <ul>@foreach ($recipe->ingredients as $i)<li>{{ $i->name }}</li>@endforeach</ul>
        <h2>Langkah</h2>
        <p>{!! nl2br(e($recipe->instructions)) !!}</p>
    </article>
</x-layout>
