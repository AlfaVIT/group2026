<?php

function view(string $page, array $data = [], bool $withLayout = true): void
{
    extract($data);
    ob_start();
    require BASE_PATH . '/templates/' . Setting::theme() . '/pages/' . $page . '.php';
    $content = ob_get_clean();
    if (!$withLayout) {
        echo $content;
        return;
    }
    require BASE_PATH . '/templates/' . Setting::theme() . '/layout.php';
}

function partial(string $name, array $data = []): void
{
    extract($data);
    require BASE_PATH . '/templates/' . Setting::theme() . '/partials/' . $name . '.php';
}

function theme_list(): array
{
    $themes = [];
    $dir = BASE_PATH . '/templates';
    foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $path) {
        $name = basename($path);
        if (is_file($path . '/layout.php')) {
            $themes[] = $name;
        }
    }
    sort($themes);
    return $themes;
}

function theme_asset(string $file): string
{
    $theme = Setting::theme();
    $path = 'templates/' . $theme . '/' . $file;
    if (!is_file(BASE_PATH . '/' . $path)) {
        return '';
    }
    return base_url() . '/' . $path . '?v=' . filemtime(BASE_PATH . '/' . $path);
}