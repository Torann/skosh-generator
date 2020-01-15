<?php

/**
 * Dump the passed variables in a JSON string and end the script.
 *
 * @return void
 */
function dd()
{
    print_r(func_get_args());
    die(1);
}

/**
 * Determine if a given string matches a given pattern.
 *
 * @param string $patterns
 * @param string $value
 *
 * @return bool
 */
function str_is($patterns, $value)
{
    if ($patterns == $value) {
        return true;
    }

    foreach (explode('|', $patterns) as $pattern) {
        $pattern = preg_quote($pattern, '#');

        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace('\*', '.*', $pattern) . '\z';

        if ((bool) preg_match('#^' . $pattern . '#', $value)) {
            return true;
        }
    }

    return false;
}

/**
 * Determine if a given string starts with a given substring.
 *
 * @param string       $haystack
 * @param string|array $needles
 *
 * @return bool
 */
function starts_with($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ($needle != '' && strpos($haystack, $needle) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Remove line breaks and double spaces
 *
 * @param string $string
 *
 * @return string
 */
function clean_string($string)
{
    return trim(preg_replace('!\s+!', ' ', $string));
}

/**
 * Turn a string into a slug.
 *
 * @param string $string
 *
 * @return string
 */
function str_slug($string)
{
    $string = preg_replace("/[^\\a-zA-Z0-9\/_\|\+\s\-]/", '', $string);
    $string = strtolower(trim($string, '-'));

    return strip_tags(preg_replace("/[\/\\\_\|\+\s\-]+/", '-', $string));
}

/**
 * Convert a value to studly caps case.
 *
 * @param string $value
 *
 * @return string
 */
function str_studly($value)
{
    $value = ucwords(str_replace(['-', '_'], ' ', $value));

    return str_replace(' ', '', $value);
}