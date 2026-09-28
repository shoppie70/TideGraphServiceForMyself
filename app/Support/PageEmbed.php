<?php

namespace App\Support;

/**
 * ページへの JSON 埋め込みヘルパー。
 */
class PageEmbed
{
    public static function renderScriptTag(array $payload, string $id = 'shioyomi-data'): string
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return '<script type="application/json" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
            . $json
            . '</script>' . "\n"
            . '<script>window.__SHIOYOMI__ = JSON.parse(document.getElementById('
            . json_encode($id)
            . ').textContent);</script>';
    }

    public static function renderMachineReadableSummary(string $text, string $label = '潮汐情報の要約'): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<section class="shioyomi-machine-summary" aria-label="' . $safeLabel . '">'
            . '<p class="visually-hidden">' . $safe . '</p>'
            . '</section>';
    }
}
