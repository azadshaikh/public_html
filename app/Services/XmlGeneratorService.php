<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\File;

class XmlGeneratorService
{
    public function generateXmlFile(string $xmlContent, string $fileType, int $fileNumber): bool
    {
        if ($xmlContent === '' || $xmlContent === '0') {
            return false;
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlContent);

        $filePath = public_path(sprintf('sitemaps/%s/', $fileType));

        if (! File::exists($filePath)) {
            File::makeDirectory($filePath, 0777, true);
        }

        $fileName = $fileNumber > 0 ? sprintf('sitemap%d.xml', $fileNumber) : 'sitemap.xml';

        return $dom->save($filePath.$fileName) !== false;
    }

    public function buildXmlHeader(string $stylesheetUrl): string
    {
        $stylesheet = sprintf('<?xml-stylesheet type="text/xsl" href="%s?sitemap=page"?>', $stylesheetUrl);

        return '<?xml version="1.0" encoding="UTF-8"?>'.$stylesheet.
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
    }

    public function buildXmlFooter(): string
    {
        return '</urlset>';
    }

    public function buildUrlEntry(string $url, string $lastModified, string $changeFreq, string $priority): string
    {
        return "<url>
            <loc><![CDATA[{$url}]]></loc>
            <lastmod><![CDATA[{$lastModified}]]></lastmod>
            <changefreq><![CDATA[{$changeFreq}]]></changefreq>
            <priority><![CDATA[{$priority}]]></priority>
        </url>";
    }

    public function clearDirectory(string $directoryPath): void
    {
        if (File::exists($directoryPath)) {
            File::deleteDirectory($directoryPath);
        }
    }
}
