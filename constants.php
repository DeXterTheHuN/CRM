<?php
/**
 * Application Constants
 * 
 * Ebben a fájlban definiáljuk az alkalmazás szintű konstansokat.
 * Ez megkönnyíti a karbantartást és elkerüli a "magic numbers" használatát.
 */

// ============================================================
// CACHE BEÁLLÍTÁSOK
// ============================================================

/**
 * Alapértelmezett cache élettartam másodpercben (1 óra)
 */
define('CACHE_TTL_DEFAULT', 3600);

/**
 * Rövid élettartamú cache másodpercben (5 perc)
 * Olyan adatokhoz használjuk, amelyek gyakrabban változnak (pl. megyék ügyfélszámai)
 */
define('CACHE_TTL_SHORT', 300);

/**
 * Cache könyvtár jogosultságok
 * 0750 = owner: rwx, group: r-x, others: ---
 */
define('CACHE_DIR_PERMISSIONS', 0750);

// ============================================================
// SESSION BEÁLLÍTÁSOK
// ============================================================

/**
 * Session élettartam másodpercben (24 óra)
 */
define('SESSION_LIFETIME_SECONDS', 86400);

// ============================================================
// PAGINATION BEÁLLÍTÁSOK
// ============================================================

/**
 * Ügyfelek száma oldalanként a megyei listában
 */
define('CLIENTS_PER_PAGE', 20);

/**
 * Pagination link-ek száma az aktuális oldal körül
 */
define('PAGINATION_RANGE', 2);

// ============================================================
// SSE (Server-Sent Events) BEÁLLÍTÁSOK
// ============================================================

/**
 * SSE kapcsolat maximális időtartama másodpercben (5 perc)
 */
define('SSE_MAX_DURATION', 300);

/**
 * SSE polling interval másodpercben
 */
define('SSE_POLL_INTERVAL', 5);

// ============================================================
// REGISZTRÁCIÓ ÉS JELSZÓ BEÁLLÍTÁSOK
// ============================================================

/**
 * Automatikus átirányítás másodpercek után sikeres regisztráció esetén
 */
define('REGISTER_REDIRECT_DELAY', 5);

/**
 * Minimum felhasználónév hossz
 */
define('USERNAME_MIN_LENGTH', 3);

/**
 * Minimum jelszó hossz (JAVASLAT: növeljük 8-ra vagy 10-re!)
 */
define('PASSWORD_MIN_LENGTH', 6);

// ============================================================
// UI ÉS STÍLUS BEÁLLÍTÁSOK
// ============================================================

/**
 * Ügyintéző színek átlátszóságának mértéke (0.0 - 1.0)
 * Az ügyfél sorok háttérszínéhez használjuk
 */
define('AGENT_COLOR_OPACITY', 0.15);

/**
 * Padding érték a card body-hoz (Bootstrap osztály neve)
 */
define('CARD_BODY_PADDING_CLASS', 'p-5');

/**
 * Bulk actions panel sticky position offset (px)
 */
define('BULK_ACTIONS_TOP_OFFSET', 80);

// ============================================================
// EGYÉB BEÁLLÍTÁSOK
// ============================================================

/**
 * Maximális megjegyzés hossz tooltip-ben történő megjelenítéshez
 */
define('MAX_NOTE_PREVIEW_LENGTH', 100);
