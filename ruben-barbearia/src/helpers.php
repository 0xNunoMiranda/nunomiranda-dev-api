<?php

function array_get(array $data, string $path, $default = null)
{
    $segments = explode('.', $path);
    $value = $data;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_phone(string $phone): string
{
    return preg_replace('/[^+0-9 ]/', '', $phone);
}

function bool_from_post(?string $value): bool
{
    return $value === '1' || $value === 'on';
}

function merge_settings(array $settings, array $updates): array
{
    foreach ($updates as $section => $values) {
        if (!is_array($values)) {
            continue;
        }
        if (!isset($settings[$section]) || !is_array($settings[$section])) {
            $settings[$section] = [];
        }
        $settings[$section] = array_merge($settings[$section], $values);
    }
    return $settings;
}
