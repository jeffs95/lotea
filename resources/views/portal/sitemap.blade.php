<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if (! empty($url['fecha']))
        <lastmod>{{ $url['fecha'] }}</lastmod>
@endif
        <priority>{{ $url['prioridad'] }}</priority>
    </url>
@endforeach
</urlset>
