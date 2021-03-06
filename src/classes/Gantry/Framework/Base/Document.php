<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Framework\Base;

use Gantry\Component\Gantry\GantryTrait;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Document
{
    use GantryTrait;

    public static $timestamp_age = 604800;

    protected static $scripts = [];
    protected static $styles = [];

    public static function addHeaderTag(array $element, $location = 'head', $priority = 0)
    {
        switch ($element['tag']) {
            case 'link':
                if (!empty($element['href']) && !empty($element['rel']) && $element['rel'] == 'stylesheet') {
                    $href = $element['href'];
                    $type = !empty($element['type']) ? $element['type'] : 'text/css';
                    $media = !empty($element['media']) ? $element['media'] : null;
                    unset($element['tag'], $element['rel'], $element['content'], $element['href'], $element['type'], $element['media']);

                    static::$styles[$location][$priority][md5($href).sha1($href)] = [
                        ':type' => 'file',
                        'href' => $href,
                        'type' => $type,
                        'media' => $media,
                        'element' => $element
                    ];

                    return true;
                }
                break;

            case 'style':
                if (!empty($element['content'])) {
                    $content = $element['content'];
                    $type = !empty($element['type']) ? $element['type'] : 'text/css';

                    static::$styles[$location][$priority][md5($content).sha1($content)] = [
                        ':type' => 'inline',
                        'content' => $content,
                        'type' => $type
                    ];

                    return true;
                }
                break;

            case 'script':
                if (!empty($element['src'])) {
                    $src = $element['src'];
                    $type = !empty($element['type']) ? $element['type'] : 'text/javascript';
                    $defer = isset($element['defer']) ? true : false;
                    $async = isset($element['async']) ? true : false;

                    static::$scripts[$location][$priority][md5($src).sha1($src)]= [
                        ':type' => 'file',
                        'src' => $src,
                        'type' => $type,
                        'defer' => $defer,
                        'async' => $async
                    ];

                    return true;

                } elseif (!empty($element['content'])) {
                    $content = $element['content'];
                    $type = !empty($element['type']) ? $element['type'] : 'text/javascript';

                    static::$scripts[$location][$priority][md5($content).sha1($content)] = [
                        ':type' => 'inline',
                        'content' => $content,
                        'type' => $type
                    ];

                    return true;
                }
                break;
        }
        return false;
    }

    public static function getStyles($location = 'head')
    {
        if (!isset(self::$styles[$location])) {
            return [];
        }

        $styles = self::$styles[$location];

        krsort($styles, SORT_NUMERIC);

        $html = [];

        foreach (self::$styles[$location] as $styles) {
            foreach ($styles as $style) {
                switch ($style[':type']) {
                    case 'file':
                        $attribs = '';
                        if ($style['media']) {
                            $attribs .= ' media="' . $style['media'] . '"';
                        }
                        $html[] = "<link rel=\"stylesheet\" href=\"{$style['href']}\" type=\"{$style['type']}\"{$attribs} />";
                        break;
                    case 'inline':
                        $attribs = '';
                        if ($style['type'] !== 'text/css') {
                            $attribs .= ' type="' . $style['type'] . '"';
                        }
                        $html[] = "<style{$attribs}>{$style['content']}</style>";
                        break;
                }
            }
        }

        return $html;
    }

    public static function getScripts($location = 'head')
    {
        if (!isset(self::$scripts[$location])) {
            return [];
        }

        $scripts = self::$scripts[$location];

        krsort($scripts, SORT_NUMERIC);

        $html = [];

        foreach (self::$scripts[$location] as $scripts) {
            foreach ($scripts as $script) {
                switch ($script[':type']) {
                    case 'file':
                        $attribs = '';
                        if ($script['async']) {
                            $attribs .= ' async="async"';
                        }
                        if ($script['defer']) {
                            $attribs .= ' async="defer"';
                        }
                        $html[] = "<script type=\"{$script['type']}\"{$attribs} src=\"{$script['src']}\"></script>";
                        break;
                    case 'inline':
                        $html[] = "<script type=\"{$script['type']}\">{$script['content']}</script>";
                        break;
                }
            }
        }

        return $html;
    }

    public static function registerAssets()
    {
    }

    public static function siteUrl()
    {
        return static::rootUri();
    }


    public static function rootUri()
    {
        return '';
    }

    public static function domain($addDomain = false)
    {
        return '';
    }


    /**
     * Return URL to the resource.
     *
     * @example {{ url('gantry-theme://images/logo.png')|default('http://www.placehold.it/150x100/f4f4f4') }}
     *
     * @param  string $url         Resource to be located.
     * @param  bool $domain        True to include domain name.
     * @param  int $timestamp_age  Append timestamp to files that are less than x seconds old. Defaults to a week.
     *                             Use value <= 0 to disable the feature.
     * @return string|null         Returns url to the resource or null if resource was not found.
     */
    public static function url($url, $domain = false, $timestamp_age = null)
    {
        if (!is_string($url) || $url === '') {
            // Return null on invalid input.
            return null;
        }

        if ($url[0] === '#') {
            // Handle fragment only.
            return $url;
        }

        $parts = self::parseUrl($url);

        if (!is_array($parts)) {
            // URL could not be parsed, return null.
            return null;
        }

        // Make sure we always have scheme, host, port and path.
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';

        if ($scheme && !$port) {
            // If URL has a scheme, we need to check if it's one of Gantry streams.
            $gantry = static::gantry();

            /** @var UniformResourceLocator $locator */
            $locator = $gantry['locator'];

            if (!$locator->schemeExists($scheme)) {
                // If scheme does not exists as a stream, assume it's external.
                return $url;
            }

            // Attempt to find the resource (because of parse_url() we need to put host back to path).
            $path = $locator->findResource("{$scheme}://{$host}{$path}", false);

            if ($path === false) {
                return null;
            }

        } elseif ($host || $port) {
            // If URL doesn't have scheme but has host or port, it is external.
            return $url;
        }

        // At this point URL is either relative or absolute path; let us find if it is relative and not . or ..
        if ($path && '/' !== $path[0] && '.' !== $path[0]) {
            if ($timestamp_age === null) {
                $timestamp_age = static::$timestamp_age;
            }
            if ($timestamp_age > 0) {
                // We want to add timestamp to the URI: do it only for existing files.
                $realPath = @realpath(GANTRY5_ROOT . '/' . $path);
                if ($realPath && is_file($realPath)) {
                    $time = filemtime($realPath);
                    // Only append timestamp for files that are less than the maximum age.
                    if ($time > time() - $timestamp_age) {
                        $parts['query'] = (!empty($parts['query']) ? "{$parts['query']}&" : '') . sprintf('%x', $time);
                    }
                }
            }

            // We need absolute URI instead of relative.
            $path = rtrim(static::rootUri(), '/') . '/' . $path;
        }

        // Set absolute URI.
        $uri = $path;

        // Add query string back.
        if (!empty($parts['query'])) {
            if (!$uri) $uri = static::rootUri();
            $uri .= '?' . $parts['query'];
        }

        // Add fragment back.
        if (!empty($parts['fragment'])) {
            if (!$uri) $uri = static::rootUri();
            $uri .= '#' . $parts['fragment'];
        }

        return static::domain($domain) . $uri;
    }

    /**
     * UTF8 aware parse_url().
     *
     * @param  string $url
     * @return array|bool
     */
    protected static function parseUrl($url)
    {
        $encodedUrl = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) { return urlencode($matches[0]); },
            $url
        );

        // PHP versions below 5.4.7 have troubles with URLs without scheme, so lets help by fixing that.
        // TODO: This is not needed in PHP >= 5.4.7, but for now we need to test if the function works.
        if ('/' === $encodedUrl[0] && false !== strpos($encodedUrl, '://')) {
            $schemeless = true;

            // Fix the path so that parse_url() will not return false.
            $parts = parse_url('fake://fake.com' . $encodedUrl);

            // Remove the fake values.
            unset($parts['scheme'], $parts['host']);

        } else {
            $parts = parse_url($encodedUrl);
        }

        if (!$parts) {
            return false;
        }

        // PHP versions below 5.4.7 do not understand schemeless URLs starting with // either.
        if (isset($schemeless) && !isset($parts['host']) && '//' == substr($encodedUrl, 0, 2)) {
            // Path is stored in format: //[host]/[path], so let's fix it.
            list($parts['host'], $path) = explode('/', substr($parts['path'], 2), 2);
            $parts['path'] = "/{$path}";
        }

        foreach($parts as $name => $value) {
            $parts[$name] = urldecode($value);
        }

        return $parts;
    }
}
