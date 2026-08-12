<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Every fact about a supported language lives here.
 *
 * Adding a fourth language means adding one entry to SUPPORTED and one folder
 * under lang/. No controller, view or middleware needs to change.
 */
final class Locales
{
    public const FALLBACK = 'ku';

    /**
     * `tag` is the BCP-47 tag emitted in <html lang> and hreflang; `ckb` is the
     * correct code for Central Kurdish (Sorani), while `ku` stays in the URL
     * because it is what readers recognise.
     */
    public const SUPPORTED = [
        'ku' => ['tag' => 'ckb', 'dir' => 'rtl', 'script' => 'arabic', 'native' => 'کوردی',   'english' => 'Kurdish'],
        'ar' => ['tag' => 'ar',  'dir' => 'rtl', 'script' => 'arabic', 'native' => 'العربية', 'english' => 'Arabic'],
        'en' => ['tag' => 'en',  'dir' => 'ltr', 'script' => 'latin',  'native' => 'English', 'english' => 'English'],
    ];

    /** @return string[] */
    public static function codes(): array
    {
        return array_keys(self::SUPPORTED);
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    /** @return array<string, string> */
    public static function meta(?string $locale = null): array
    {
        $locale = self::supports($locale) ? $locale : app()->getLocale();

        return self::SUPPORTED[$locale] ?? self::SUPPORTED[self::FALLBACK];
    }

    public static function direction(?string $locale = null): string
    {
        return self::meta($locale)['dir'];
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::direction($locale) === 'rtl';
    }

    public static function tag(?string $locale = null): string
    {
        return self::meta($locale)['tag'];
    }

    /**
     * Resolve the best language for a visitor who has not chosen one yet:
     * remembered choice first, then the browser's Accept-Language header,
     * then the fallback.
     */
    public static function preferred(Request $request): string
    {
        $remembered = $request->session()->get('locale');
        if (self::supports($remembered)) {
            return $remembered;
        }

        $browser = $request->getPreferredLanguage(self::negotiableTags());
        if ($browser !== null) {
            foreach (self::SUPPORTED as $code => $meta) {
                if (str_starts_with(strtolower($browser), strtolower($meta['tag']))) {
                    return $code;
                }
            }
        }

        return self::FALLBACK;
    }

    /** @return string[] */
    private static function negotiableTags(): array
    {
        // `ku` is accepted alongside `ckb` because some browsers still send the
        // macrolanguage code for Kurdish.
        return array_merge(array_column(self::SUPPORTED, 'tag'), ['ku']);
    }

    /**
     * The current URL rewritten into another language.
     *
     * Used by both the language switcher and the hreflang tags, so a visitor who
     * switches language while reading a result stays on that result instead of
     * being sent back to the upload form.
     */
    public static function urlFor(string $locale): string
    {
        $request = request();
        $segments = $request->segments();

        if ($segments === [] || ! self::supports($segments[0] ?? null)) {
            array_unshift($segments, $locale);
        } else {
            $segments[0] = $locale;
        }

        $query = $request->getQueryString();

        return url(implode('/', $segments)) . ($query ? '?' . $query : '');
    }
}
