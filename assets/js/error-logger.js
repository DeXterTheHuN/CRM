// Global JavaScript Error Handler
// Captures ALL JavaScript errors and sends to server

(function () {
    // Rate limiting to prevent 508 Loop Detected errors
    let errorCount = 0;
    let lastErrorReset = Date.now();
    const MAX_ERRORS_PER_PERIOD = 10; // Max 10 errors per 30 seconds
    const ERROR_RESET_PERIOD = 30000; // 30 seconds

    // URLs to NEVER log (to prevent infinite loops)
    const EXCLUDED_URLS = [
        'log_js_error.php',      // Self - would cause infinite loop
        'notifications_sse.php', // SSE endpoints - expected to fail sometimes
        'notifications_api.php', // Notifications API - can fail on 508
        'api_settlements.php'    // Settlements API - race condition on page load
    ];

    function shouldLogError(url) {
        // Don't log excluded URLs
        if (url && EXCLUDED_URLS.some(excluded => url.includes(excluded))) {
            return false;
        }

        // Rate limiting check
        const now = Date.now();
        if (now - lastErrorReset > ERROR_RESET_PERIOD) {
            errorCount = 0;
            lastErrorReset = now;
        }

        if (errorCount >= MAX_ERRORS_PER_PERIOD) {
            return false; // Rate limited
        }

        errorCount++;
        return true;
    }

    // Capture uncaught errors
    window.addEventListener('error', function (event) {
        if (!shouldLogError(event.filename)) return;

        logJSError({
            message: event.message || 'Unknown error',
            file: event.filename || window.location.href,
            line: event.lineno || 0,
            column: event.colno || 0,
            stack: event.error?.stack || '',
            type: 'error',
            severity: 'ERROR'
        });
    });

    // Capture unhandled promise rejections
    window.addEventListener('unhandledrejection', function (event) {
        if (!shouldLogError(window.location.href)) return;

        logJSError({
            message: event.reason?.message || event.reason || 'Unhandled Promise Rejection',
            file: window.location.href,
            line: 0,
            column: 0,
            stack: event.reason?.stack || '',
            type: 'unhandledrejection',
            severity: 'ERROR'
        });
    });

    // Send error to server
    function logJSError(errorData) {
        errorData.url = window.location.href;
        errorData.browser = navigator.userAgent;

        // Use sendBeacon for reliability (works even on page unload)
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(errorData)], { type: 'application/json' });
            navigator.sendBeacon('log_js_error.php', blob);
        } else {
            // Fallback to fetch
            fetch('log_js_error.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(errorData)
            }).catch(() => {
                // Silent fail - don't cause more errors
            });
        }

        // Also log to console in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('[JS Error Logged]', errorData);
        }
    }

    // ===== HTTP ERROR INTERCEPTOR =====
    // Intercept fetch() calls to log HTTP errors (404, 500, 508, etc)
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';

        return originalFetch.apply(this, args)
            .then(response => {
                // Log HTTP errors (4xx, 5xx) - but respect exclusions
                if (!response.ok && shouldLogError(url)) {
                    logJSError({
                        message: `HTTP ${response.status} ${response.statusText}`,
                        file: url,
                        line: 0,
                        column: 0,
                        stack: `fetch('${url}') returned ${response.status}`,
                        type: 'http_error',
                        severity: response.status >= 500 ? 'ERROR' : 'WARNING'
                    });
                }
                return response;
            })
            .catch(error => {
                // Network errors (no response) - but respect exclusions
                if (shouldLogError(url)) {
                    logJSError({
                        message: error.message || 'Network error',
                        file: url,
                        line: 0,
                        column: 0,
                        stack: error.stack || `fetch('${url}') failed`,
                        type: 'network_error',
                        severity: 'ERROR'
                    });
                }
                throw error; // Re-throw to preserve original behavior
            });
    };

    // Intercept XMLHttpRequest for older AJAX calls
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this._url = url;
        this._method = method;
        return originalOpen.apply(this, [method, url, ...rest]);
    };

    XMLHttpRequest.prototype.send = function (...args) {
        const self = this;

        this.addEventListener('load', function () {
            if (this.status >= 400 && shouldLogError(self._url)) {
                logJSError({
                    message: `HTTP ${this.status} ${this.statusText}`,
                    file: self._url,
                    line: 0,
                    column: 0,
                    stack: `${self._method} ${self._url} returned ${this.status}`,
                    type: 'http_error',
                    severity: this.status >= 500 ? 'ERROR' : 'WARNING'
                });
            }
        });

        this.addEventListener('error', function () {
            if (shouldLogError(self._url)) {
                logJSError({
                    message: 'Network request failed',
                    file: self._url,
                    line: 0,
                    column: 0,
                    stack: `${self._method} ${self._url} failed`,
                    type: 'network_error',
                    severity: 'ERROR'
                });
            }
        });

        return originalSend.apply(this, args);
    };
})();
