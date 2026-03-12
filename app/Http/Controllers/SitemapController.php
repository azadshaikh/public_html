<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        if (! empty(setting('seo_sitemap_enable_sitemap', false))) {
            $data = [];

            $data['sitemapindexies'] = [];

            $sitemap_sequence = [
                'classified/ads' => 'Classified Ads',
                'posts' => 'Posts',
                'categories' => 'Categories',
                'tags' => 'Tags',
                'pages' => 'Pages',
                'authors' => 'Authors',
            ];
            $sitemap_path = public_path('sitemaps');
            foreach (array_keys($sitemap_sequence) as $slug) {
                $sitemap_folder_path = $sitemap_path.'/'.$slug;
                if (is_dir($sitemap_folder_path)) {
                    $files = array_values(array_filter(scandir($sitemap_folder_path), fn ($file): bool => preg_match('/^sitemap(?:\d+)?\.xml$/', (string) $file) === 1));

                    if ($files !== []) {
                        natsort($files);

                        foreach ($files as $filename) {
                            $filePath = $sitemap_folder_path.'/'.$filename;
                            if (! file_exists($filePath)) {
                                continue;
                            }

                            $last_mod_datetime = sitemap_date_time_format(filemtime($filePath));
                            $data['sitemapindexies'][] = [
                                'url' => url('sitemaps/'.$slug.'/'.$filename),
                                'updated_at' => $last_mod_datetime,
                            ];
                        }
                    }
                }
            }

            $data['stylefile'] = asset('css/sitemap.xsl');

            return response()->view('sitemap', $data)->header('Content-Type', 'text/xml');
        }

        abort(404);
    }
}
