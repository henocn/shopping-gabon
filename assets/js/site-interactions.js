(function(window, document) {
    'use strict';

    class TrackingManager {
        constructor(config = {}) {
            this.config = this.mergeConfig(config);
            this.isReady = false;
            this.pageViewTracked = Boolean(window.fbPageViewTracked);
            this.eventQueue = [];
            this.scriptsLoaded = { facebook: false, googleAnalytics: false, tiktok: false };
            this.init();
        }

        mergeConfig(config) {
            var defaults = {
                facebook: {
                    enabled: false,
                    pixels: [],
                    timeout: 5000
                },
                googleAnalytics: {
                    enabled: false,
                    trackingId: null
                },
                tiktok: {
                    enabled: false,
                    pixelId: null
                },
                debug: false
            };

            return {
                ...defaults,
                ...config,
                facebook: {
                    ...defaults.facebook,
                    ...(config.facebook || {}),
                    pixels: this.normalizePixels((config.facebook && config.facebook.pixels) || defaults.facebook.pixels)
                },
                googleAnalytics: {
                    ...defaults.googleAnalytics,
                    ...(config.googleAnalytics || {})
                },
                tiktok: {
                    ...defaults.tiktok,
                    ...(config.tiktok || {})
                }
            };
        }

        normalizePixels(pixels) {
            if (!Array.isArray(pixels)) return [];

            var normalized = [];
            pixels.forEach(function(pixelId) {
                pixelId = String(pixelId || '').trim();
                if (/^\d+$/.test(pixelId) && normalized.indexOf(pixelId) === -1) {
                    normalized.push(pixelId);
                }
            });

            return normalized;
        }

        init() {
            try {
                this.initFacebook();
                this.initGoogleAnalytics();
                this.initTikTok();
            } finally {
                this.isReady = true;
                this.processQueue();
            }
        }

        initFacebook() {
            if (!this.config.facebook.enabled || this.config.facebook.pixels.length === 0) {
                return;
            }

            this.installFacebookBase();

            if (typeof window.fbq !== 'function') {
                return;
            }

            window.fbInitializedPixels = this.normalizePixels(window.fbInitializedPixels || []);

            this.config.facebook.pixels.forEach(function(pixelId) {
                if (window.fbInitializedPixels.indexOf(pixelId) === -1) {
                    window.fbq('init', pixelId);
                    window.fbInitializedPixels.push(pixelId);
                }
            });

            if (!window.fbPageViewTracked) {
                window.fbPageViewTracked = true;
                this.track('PageView', {}, ['facebook']);
            }

            this.pageViewTracked = true;
            this.scriptsLoaded.facebook = true;
        }

        installFacebookBase() {
            if (typeof window.fbq === 'function') {
                return;
            }

            !(function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() {
                    n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = true;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = true;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s);
            })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        }

        initGoogleAnalytics() {}

        initTikTok() {}

        generateEventId() {
            var ts = Date.now().toString(36);
            var rand = Math.random().toString(36).substring(2, 10);
            return ts + '_' + rand;
        }

        getCookie(name) {
            var cookies = document.cookie ? document.cookie.split('; ') : [];
            for (var i = 0; i < cookies.length; i += 1) {
                var parts = cookies[i].split('=');
                var key = decodeURIComponent(parts.shift());
                if (key === name) {
                    return decodeURIComponent(parts.join('='));
                }
            }
            return '';
        }

        getFbp() {
            return this.getCookie('_fbp');
        }

        getFbc() {
            var fbc = this.getCookie('_fbc');
            if (fbc) return fbc;

            try {
                var params = new URLSearchParams(window.location.search);
                var fbclid = params.get('fbclid');
                if (fbclid) {
                    return 'fb.1.' + Date.now() + '.' + fbclid;
                }
            } catch (error) {}

            return '';
        }

        getBrowserData() {
            return {
                fbp: this.getFbp(),
                fbc: this.getFbc()
            };
        }

        track(eventName, eventData = {}, platforms = ['facebook'], options = {}) {
            if (!this.isReady) {
                this.eventQueue.push({ eventName: eventName, eventData: eventData, platforms: platforms, options: options });
                return;
            }

            platforms.forEach((platform) => {
                try {
                    if (platform === 'facebook') {
                        this.trackFacebook(eventName, eventData, options);
                    }
                } catch (error) {
                    this.debug('Tracking error', error);
                }
            });
        }

        trackFacebook(eventName, eventData = {}, options = {}) {
            if (!this.config.facebook.enabled || this.config.facebook.pixels.length === 0) {
                return;
            }

            var eventId = options.eventID || this.generateEventId();

            if (typeof window.fbq !== 'function') {
                this.trackFacebookViaImage(eventName, eventData, { eventID: eventId });
                if (!options.skipServerRelay) {
                    this.mirrorToServer(eventName, eventData, eventId);
                }
                return;
            }

            var standardEvents = [
                'PageView',
                'Purchase',
                'Lead',
                'InitiateCheckout',
                'ViewContent',
                'CompleteRegistration',
                'AddToCart',
                'AddPaymentInfo',
                'Search'
            ];

            var command = standardEvents.indexOf(eventName) === -1 ? 'trackCustom' : 'track';
            window.fbq(command, eventName, eventData, { eventID: eventId });

            if (!options.skipServerRelay) {
                this.mirrorToServer(eventName, eventData, eventId);
            }
        }

        trackFacebookViaImage(eventName, eventData = {}, options = {}) {
            this.config.facebook.pixels.forEach(function(pixelId) {
                try {
                    var params = new URLSearchParams({
                        id: pixelId,
                        ev: eventName,
                        noscript: '1',
                        dl: window.location.href,
                        t: Date.now()
                    });

                    if (eventData.value) params.append('cd[value]', eventData.value);
                    if (eventData.currency) params.append('cd[currency]', eventData.currency);
                    if (eventData.content_ids) params.append('cd[content_ids]', JSON.stringify(eventData.content_ids));
                    if (eventData.content_type) params.append('cd[content_type]', eventData.content_type);
                    if (eventData.num_items) params.append('cd[num_items]', eventData.num_items);
                    if (options.eventID) params.append('eid', options.eventID);

                    var img = new Image();
                    img.src = 'https://www.facebook.com/tr?' + params.toString();
                } catch (error) {}
            });
        }

        /**
         * Filet serveur : relaie l'événement à capi-relay.php pour couvrir les cas où
         * un bloqueur de pub empêche fbq/fbevents.js de fonctionner dans le navigateur.
         * Best-effort, ne doit jamais impacter l'expérience utilisateur.
         */
        mirrorToServer(eventName, eventData, eventId) {
            try {
                var browserData = this.getBrowserData();
                var payload = JSON.stringify({
                    event_name: eventName,
                    event_id: eventId,
                    custom_data: eventData,
                    fbp: browserData.fbp,
                    fbc: browserData.fbc,
                    source_url: window.location.href
                });

                if (navigator.sendBeacon) {
                    navigator.sendBeacon('/capi-relay.php', new Blob([payload], { type: 'application/json' }));
                } else if (typeof fetch === 'function') {
                    fetch('/capi-relay.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: payload,
                        keepalive: true
                    }).catch(function() {});
                }
            } catch (error) {
                this.debug('Server relay error', error);
            }
        }

        processQueue() {
            while (this.eventQueue.length > 0) {
                var event = this.eventQueue.shift();
                this.track(event.eventName, event.eventData, event.platforms, event.options || {});
            }
        }

        addFacebookPixel(pixelId) {
            pixelId = String(pixelId || '').trim();
            if (!/^\d+$/.test(pixelId) || this.config.facebook.pixels.indexOf(pixelId) !== -1) {
                return;
            }

            this.config.facebook.pixels.push(pixelId);
            this.config.facebook.enabled = true;
            this.initFacebook();
        }

        removeFacebookPixel(pixelId) {
            var index = this.config.facebook.pixels.indexOf(String(pixelId));
            if (index > -1) {
                this.config.facebook.pixels.splice(index, 1);
            }
        }

        debug(message, payload) {
            if (this.config.debug && window.console && typeof window.console.warn === 'function') {
                window.console.warn('[TrackingManager] ' + message, payload || '');
            }
        }
    }

    window.TrackingManager = TrackingManager;

    if (!window.trackingManager) {
        window.trackingManager = new TrackingManager(window.trackingManagerConfig || {});
    }

    if (!window.trackEvent) {
        window.trackEvent = function(eventName, eventData, platforms, options) {
            if (!platforms) platforms = ['facebook'];
            if (!options) options = {};
            if (window.trackingManager) {
                window.trackingManager.track(eventName, eventData || {}, platforms, options);
            }
        };
    }
})(window, document);
