<?php

require_once __DIR__ . "/company_settings_helper.php";

function get_branding_settings()
{
    static $branding = null;

    if ($branding !== null && empty($GLOBALS['__company_settings_cache_reset'])) {
        return $branding;
    }

    $settings = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli
        ? company_settings_get($GLOBALS['conn'])
        : company_settings_defaults();

    $branding = [
        'company_name' => $settings['company_name'],
        'tagline' => $settings['tagline'],
        'contact' => $settings['contact'],
        'address' => $settings['address'],
        'email' => $settings['email'],
        'prepared_by_label' => $settings['prepared_by_label'],
        'checked_by_label' => $settings['checked_by_label'],
        'approved_by_label' => $settings['approved_by_label'],
        'print_footer' => $settings['print_footer'],
        'logo_url' => '',
        'logo_path' => '',
    ];

    $projectRoot = dirname(__DIR__);
    $logoDir = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo';
    $candidates = [
        'logo.png',
        'logo.jpg',
        'logo.jpeg',
        'logo.webp',
        'company-logo.png',
        'company-logo.jpg',
        'company-logo.jpeg',
        'company-logo.webp',
    ];

    $configuredLogo = trim((string) ($settings['logo_path'] ?? ''));
    if ($configuredLogo !== '') {
        $normalizedConfigured = ltrim(str_replace('\\', '/', $configuredLogo), '/');
        $configuredFullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedConfigured);
        if (is_file($configuredFullPath)) {
            $branding['logo_path'] = $configuredFullPath;
            $branding['logo_url'] = '/accounting-system/' . $normalizedConfigured;
            unset($GLOBALS['__company_settings_cache_reset']);
            return $branding;
        }
    }

    foreach ($candidates as $candidate) {
        $fullPath = $logoDir . DIRECTORY_SEPARATOR . $candidate;
        if (is_file($fullPath)) {
            $branding['logo_path'] = $fullPath;
            $branding['logo_url'] = '/accounting-system/assets/logo/' . $candidate;
            unset($GLOBALS['__company_settings_cache_reset']);
            return $branding;
        }
    }

    $imageDir = $projectRoot . DIRECTORY_SEPARATOR . 'images';
    foreach ($candidates as $candidate) {
        $fullPath = $imageDir . DIRECTORY_SEPARATOR . $candidate;
        if (is_file($fullPath)) {
            $branding['logo_path'] = $fullPath;
            $branding['logo_url'] = '/accounting-system/images/' . $candidate;
            unset($GLOBALS['__company_settings_cache_reset']);
            return $branding;
        }
    }

    if (is_dir($logoDir)) {
        $matches = glob($logoDir . DIRECTORY_SEPARATOR . '*.{png,jpg,jpeg,webp}', GLOB_BRACE);
        if (!empty($matches)) {
            $firstLogo = basename($matches[0]);
            $branding['logo_path'] = $matches[0];
            $branding['logo_url'] = '/accounting-system/assets/logo/' . $firstLogo;
        }
    }

    if (empty($branding['logo_url']) && is_dir($imageDir)) {
        $matches = glob($imageDir . DIRECTORY_SEPARATOR . '*.{png,jpg,jpeg,webp}', GLOB_BRACE);
        if (!empty($matches)) {
            $firstLogo = basename($matches[0]);
            $branding['logo_path'] = $matches[0];
            $branding['logo_url'] = '/accounting-system/images/' . $firstLogo;
        }
    }

    unset($GLOBALS['__company_settings_cache_reset']);
    return $branding;
}
