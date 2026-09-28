<?php

use App\Support\Site;

if (!class_exists(Site::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$page_title = $page_title ?? Site::defaultTitle();
$page_description = $page_description ?? Site::APP_DESCRIPTION;
$page_canonical = $page_canonical ?? Site::url('');
$page_og_type = $page_og_type ?? 'website';
$page_robots = $page_robots ?? 'index, follow';
$page_image = $page_image ?? (Site::publicBaseUrl() . '/assets/img/ogp.jpg');
$asset_version = $asset_version ?? '20260928b';

$json_ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => Site::publicBaseUrl() . '/#website',
            'name' => Site::APP_NAME,
            'url' => Site::publicBaseUrl() . '/',
            'description' => Site::APP_DESCRIPTION,
            'inLanguage' => 'ja',
            'publisher' => [
                '@type' => 'Person',
                'name' => Site::AUTHOR,
            ],
        ],
        [
            '@type' => 'WebApplication',
            '@id' => Site::publicBaseUrl() . '/#app',
            'name' => Site::APP_NAME,
            'url' => Site::publicBaseUrl() . '/',
            'applicationCategory' => 'LifestyleApplication',
            'operatingSystem' => 'Any',
            'browserRequirements' => 'Requires JavaScript',
            'description' => Site::APP_DESCRIPTION,
            'inLanguage' => 'ja',
            'image' => Site::publicBaseUrl() . '/assets/img/ogp.jpg',
            'author' => [
                '@type' => 'Person',
                'name' => Site::AUTHOR,
            ],
        ],
    ],
];

if (!empty($page_json_ld_extra) && is_array($page_json_ld_extra)) {
    $json_ld['@graph'][] = $page_json_ld_extra;
}

// OGP 画像は本番 URL を既定にする（ローカルホストを SNS に載せない）
if (!isset($page_image) || $page_image === '' || str_contains((string)$page_image, '127.0.0.1') || str_contains((string)$page_image, 'localhost')) {
    $page_image = Site::publicBaseUrl() . '/assets/img/ogp.jpg';
}
?>
<!DOCTYPE HTML>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0, viewport-fit=cover"/>
    <base href="<?php echo htmlspecialchars(Site::baseHref(), ENT_QUOTES, 'UTF-8'); ?>">

    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="keywords" content="潮見表, シオヨミ, 潮汐, 天気, 風速, 釣行"/>
    <meta name="author" content="<?php echo htmlspecialchars(Site::AUTHOR, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="robots" content="<?php echo htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="theme-color" content="<?php echo htmlspecialchars(Site::themeColor(), ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="application-name" content="<?php echo htmlspecialchars(Site::APP_NAME, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="default"/>
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(Site::APP_NAME, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="mobile-web-app-capable" content="yes"/>

    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>"/>

    <link rel="manifest" href="<?php echo htmlspecialchars(Site::path('manifest.webmanifest'), ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars(Site::path('assets/img/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars(Site::path('assets/img/favicon-32.png'), ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars(Site::path('assets/img/icon-192.png'), ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(Site::path('assets/img/icon.png'), ENT_QUOTES, 'UTF-8'); ?>"/>

    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:url" content="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:type" content="<?php echo htmlspecialchars($page_og_type, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:site_name" content="<?php echo htmlspecialchars(Site::APP_NAME, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:locale" content="ja_JP"/>

    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="twitter:site" content="<?php echo htmlspecialchars(Site::TWITTER_SITE, ENT_QUOTES, 'UTF-8'); ?>"/>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(Site::path('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode((string)$asset_version); ?>"/>
    <style>
        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        .shioyomi-machine-summary {
            margin: 0;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.js"></script>
    <script type="application/ld+json"><?php
        echo json_encode($json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    ?></script>
</head>

<body>
