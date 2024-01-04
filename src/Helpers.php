<?php

declare(strict_types=1);

namespace Esst\TranslationExtractor;

class Helpers
{
    public static function arrayDiffKeyRecursive($array1, $array2, &$unset_keys = []): array
    {
        foreach ($array1 as $key => $value) {
            if (\is_array($value)) {
                if (! \array_key_exists($key, $array2) || ! \is_array($array2[$key])) {
                    $unset_keys[] = $key;

                    unset($array1[$key]);
                } else {
                    $array1[$key] = static::arrayDiffKeyRecursive($value, $array2[$key], $unset_keys);

                    if (\count($array1[$key]) === 0) {
                        $unset_keys[] = $key;

                        unset($array1[$key]);
                    }
                }
            } else {
                if (! \array_key_exists($key, $array2)) {
                    $unset_keys[] = $key;

                    unset($array1[$key]);

                    continue;
                }

                if ($value === '' && $array2[$key] !== '') {
                    $array1[$key] = $array2[$key];
                }

                if ($value === null && $array2[$key] !== null) {
                    $array1[$key] = $array2[$key];
                }

                if (\is_string($value) && \is_array($array2[$key])) {
                    $unset_keys[] = $key;

                    unset($array1[$key]);
                }
            }
        }

        return $array1;
    }

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     *
     * Taken from https://github.com/laravel/framework/blob/10.x/src/Illuminate/Collections/Arr.php#L131
     */
    public static function arrayUndot(iterable $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            static::arraySet($results, $key, $value);
        }

        return $results;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * Taken from https://github.com/laravel/framework/blob/10.x/src/Illuminate/Collections/Arr.php#L699
     */
    public static function arraySet(array &$array, string | int | null $key, mixed $value): array
    {
        if (\is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (\count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! \is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     *
     * Taken from https://github.com/laravel/framework/blob/10.x/src/Illuminate/Support/Str.php#L98
     */
    public static function stringAfterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + \strlen($search));
    }
}
