<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recipe PWA</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2e7d32">
    @livewireStyles
</head>
<body>
    <main>{{ $slot }}</main>
    @livewireScripts
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
    </script>
</body>
</html>
