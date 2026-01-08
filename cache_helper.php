<?php
// Egyszerű fájl alapú cache kezelő
// A cache fájlok a "cache" könyvtárban lesznek tárolva.

/**
 * Lekéri a cache-ből az adatot a megadott kulcshoz.
 *
 * A cache a "cache" könyvtárban tárolt JSON formátumban van. Ha a fájl nem létezik
 * vagy a TTL (időkorlát) lejárt, akkor false értékkel tér vissza.
 *
 * BIZTONSÁGI JAVÍTÁS: serialize/unserialize helyett JSON használata,
 * elkerülve az object injection támadásokat.
 *
 * @param string $key A cache kulcs
 * @param int $ttl Másodpercben megadott élettartam; 0 vagy kisebb érték esetén nincs lejárat
 * @return mixed|false A cache-elt adat vagy false, ha nincs érvényes cache
 */
function cache_get(string $key, int $ttl = CACHE_TTL_DEFAULT)
{
    $cacheDir = __DIR__ . '/cache';
    $file = $cacheDir . '/' . md5($key) . '.cache';

    // Ha nincs cache könyvtár vagy fájl, nincs cache
    if (!is_file($file)) {
        return false;
    }

    // TTL ellenőrzés
    if ($ttl > 0 && (filemtime($file) + $ttl) < time()) {
        // Lejárt cache törlése
        @unlink($file);
        return false;
    }

    // Fájl tartalom olvasása
    $data = @file_get_contents($file);
    if ($data === false || $data === '') {
        return false;
    }

    // JSON dekódolás - BIZTONSÁGOS (nincs object injection kockázat)
    $decoded = json_decode($data, true); // true = asszociatív tömb

    // JSON error ellenőrzés
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Hibás cache fájl törlése
        @unlink($file);

        // Simple error logging - NO ErrorHandler to avoid loops!
        error_log('Cache JSON decode error: ' . json_last_error_msg() . ' for key: ' . $key);

        return false;
    }

    return $decoded;
}

/**
 * Elmenti az adatot a cache-be a megadott kulcs alatt.
 *
 * BIZTONSÁGI JAVÍTÁS: serialize() helyett JSON encoding,
 * amely biztonságosabb és átláthatóbb.
 *
 * @param string $key A cache kulcs
 * @param mixed $data A mentendő adat (JSON-kompatibilis típus)
 * @return bool Sikeres volt-e a mentés
 */
function cache_set(string $key, $data): bool
{
    $cacheDir = __DIR__ . '/cache';

    // Cache könyvtár létrehozása, ha nem létezik
    if (!is_dir($cacheDir)) {
        if (!@mkdir($cacheDir, CACHE_DIR_PERMISSIONS, true)) {
            error_log('Failed to create cache directory: ' . $cacheDir);
            return false;
        }
    }

    $file = $cacheDir . '/' . md5($key) . '.cache';

    // JSON encoding - BIZTONSÁGOS, ember által is olvasható
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // JSON encoding error ellenőrzés
    if ($encoded === false) {
        error_log('Cache JSON encode error: ' . json_last_error_msg() . ' for key: ' . $key);
        return false;
    }

    // Fájlba írás
    $result = @file_put_contents($file, $encoded, LOCK_EX);

    if ($result === false) {
        ErrorHandler::logAppError('Failed to write cache file', [
            'key' => $key,
            'file' => $file
        ]);
        return false;
    }

    return true;
}

/**
 * Cache invalidálás - töröl egy kulcsot
 *
 * @param string $key A törlendő cache kulcs
 * @return bool Sikeres volt-e a törlés
 */
function cache_delete(string $key): bool
{
    $cacheDir = __DIR__ . '/cache';
    $file = $cacheDir . '/' . md5($key) . '.cache';

    if (is_file($file)) {
        return @unlink($file);
    }

    return true; // Ha nincs ilyen fájl, akkor "sikeres" a törlés
}