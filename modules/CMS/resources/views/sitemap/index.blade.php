{!! '<' . '?xml version="1.0" encoding="UTF-8"?>' !!}
@if (!empty($stylefile))
    {!! '<' . '?xml-stylesheet type="text/xsl" href="' . $stylefile . '?stype=root"?>' !!}
@endif
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($sitemapindexies as $sitmapdata)
        <sitemap>
            <loc>
                <![CDATA[{{ $sitmapdata['url'] }}]]>
            </loc>
            <lastmod>
                <![CDATA[{{ $sitmapdata['updated_at'] }}]]>
            </lastmod>
        </sitemap>
    @endforeach
</sitemapindex>
