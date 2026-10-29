<?php

function view(string $page, array $data = [], bool $withLayout = true): void
{
    extract($data);
    ob_start();
    require BASE_PATH . '/templates/' . theme_dir() . '/pages/' . $page . '.php';
    $content = ob_get_clean();
    if (!$withLayout) {
        echo $content;
        return;
    }
    require BASE_PATH . '/templates/' . theme_dir() . '/layout.php';
}

function partial(string $name, array $data = []): void
{
    extract($data);
    require BASE_PATH . '/templates/' . theme_dir() . '/partials/' . $name . '.php';
}

function theme_dir(): string
{
    $t = Setting::theme();
    return is_dir(BASE_PATH . '/templates/' . $t) && is_file(BASE_PATH . '/templates/' . $t . '/layout.php')
        ? $t
        : 'default';
}

function theme_list(): array
{
    return THEMES;
}

function theme_scheme(): string
{
    $t = Setting::theme();
    return in_array($t, THEMES, true) ? $t : DEFAULT_THEME;
}

function user_theme(?array $user = null): string
{
    $user ??= Auth::user();
    $scheme = $user['theme'] ?? '';
    return $scheme !== '' && $scheme !== 'default' && in_array($scheme, THEMES, true)
        ? $scheme
        : theme_scheme();
}

function theme_scheme_css(?array $user = null): string
{
    $scheme = user_theme($user);
    if ($scheme === 'default') {
        return '';
    }
    $path = 'templates/' . theme_dir() . '/theme-' . $scheme . '.css';
    if (!is_file(BASE_PATH . '/' . $path)) {
        return '';
    }
    return base_url() . '/' . $path . '?v=' . filemtime(BASE_PATH . '/' . $path);
}

function theme_asset(string $file): string
{
    $theme = theme_dir();
    $path = 'templates/' . $theme . '/' . $file;
    if (!is_file(BASE_PATH . '/' . $path)) {
        return '';
    }
    return base_url() . '/' . $path . '?v=' . filemtime(BASE_PATH . '/' . $path);
}