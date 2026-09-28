<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Support\Site;

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = Site::publicBaseUrl();
$today = gmdate('Y-m-d');

$urls = [
    ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $base . '/index.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/llms.txt', 'priority' => '0.5', 'changefreq' => 'weekly'],
    ['loc' => $base . '/api/places.php', 'priority' => '0.6', 'changefreq' => 'weekly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?php echo htmlspecialchars($u['loc'], ENT_XML1); ?></loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq><?php echo $u['changefreq']; ?></changefreq>
    <priority><?php echo $u['priority']; ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
