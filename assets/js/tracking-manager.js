class TrackingManager {
    constructor(config = {}) {
        var defaultConfig = {
            facebook: {
                enabled: true,
                pixels: ['1536994954069676','1373481401089526'],
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
            debug: false,
        };
        this.config = {
            ...defaultConfig,
            ...config,
            facebook: {
                ...defaultConfig.facebook,
                ...(config.facebook || {})
            },
            googleAnalytics: {
                ...defaultConfig.googleAnalytics,
                ...(config.googleAnalytics || {})
            },
            tiktok: {
                ...defaultConfig.tiktok,
                ...(config.tiktok || {})
            }
        };
        this.isReady = false;
        this.pageViewTracked = false;
        this.eventQueue = [];
        this.scriptsLoaded = { facebook: false, googleAnalytics: false, tiktok: false };
        this.init();
    }

    async init() {
        try {
            if (this.config.facebook.enabled) await this.initFacebook();
            if (this.config.googleAnalytics.enabled) await this.initGoogleAnalytics();
            if (this.config.tiktok.enabled) await this.initTikTok();
            this.isReady = true;
            this.processQueue();
            this.ensurePageView();
        } catch (error) {
            this.isReady = true;
            this.processQueue();
            this.ensurePageView();
        }
    }

    async initFacebook() {
        try {
            // Éviter la double initialisation
            if (window.fbEventsInitialized) {
                return;
            }
            window.fbEventsInitialized = true;

            // Snippet officiel Meta Pixel pour éviter les conflits de versions
            !(function(f,b,e,v,n,t,s){
                if(f.fbq) return; n=f.fbq=function(){ n.callMethod ?
                    n.callMethod.apply(n,arguments) : n.queue.push(arguments) };
                if(!f._fbq) f._fbq=n; n.push=n; n.loaded=!0; n.version='2.0';
                n.queue=[]; t=b.createElement(e); t.async=!0; t.src=v;
                s=b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t,s);
            })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

            // Alias de compatibilité
            window._fbq = window._fbq || window.fbq;

            // Initialiser seulement les pixels non initialisés
            if (!window.fbInitializedPixels) window.fbInitializedPixels = [];
            this.config.facebook.pixels.forEach(pixelId => {
                if (!window.fbInitializedPixels.includes(pixelId)) {
                    fbq('init', pixelId);
                    window.fbInitializedPixels.push(pixelId);
                }
            });

            fbq('track', 'PageView');
            this.pageViewTracked = true;
            this.scriptsLoaded.facebook = true;
        } catch (error) {
            // Pas de fallback immédiat ici; les événements utiliseront l'image si fbq indisponible
        }
    }

    ensurePageView() {
        if (this.pageViewTracked || !this.config.facebook.enabled) return;
        this.pageViewTracked = true;
        this.track('PageView', {}, ['facebook']);
    }

    loadFacebookScript() {
        try {
            // Éviter de charger le script plusieurs fois
            if (document.querySelector('script[src*="fbevents.js"]') || window.fbScriptLoaded) {
                return;
            }
            window.fbScriptLoaded = true;

            const script = document.createElement('script');
            script.async = true;
            script.src = 'https://connect.facebook.net/en_US/fbevents.js';
            const timeout = setTimeout(() => {}, this.config.facebook.timeout);
            script.onload = () => clearTimeout(timeout);
            script.onerror = () => clearTimeout(timeout);
            const firstScript = document.getElementsByTagName('script')[0];
            if (firstScript && firstScript.parentNode) {
                firstScript.parentNode.insertBefore(script, firstScript);
            }
        } catch (error) {}
    }

    addFacebookFallbackImages() {
        this.config.facebook.pixels.forEach(pixelId => {
            const noscript = document.createElement('noscript');
            noscript.innerHTML = '<img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' + pixelId + '&ev=PageView&noscript=1" />';
            document.head.appendChild(noscript);
        });
    }

    /**
     * Génère un identifiant unique pour la déduplication Pixel ↔ CAPI.
     * Format : timestamp base36 + random base36 → ~20 caractères.
     * @returns {string}
     */
    generateEventId() {
        var ts = Date.now().toString(36);
        var rand = Math.random().toString(36).substring(2, 10);
        return ts + '_' + rand;
    }

    /**
     * Lit un cookie par nom.
     * @param {string} name
     * @returns {string}
     */
    getCookie(name) {
        var cookies = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < cookies.length; i++) {
            var parts = cookies[i].split('=');
            var key = decodeURIComponent(parts.shift());
            if (key === name) {
                return decodeURIComponent(parts.join('='));
            }
        }
        return '';
    }

    /**
     * Retourne le cookie _fbp (First-Party Browser Pixel cookie).
     * @returns {string}
     */
    getFbp() {
        return this.getCookie('_fbp');
    }

    /**
     * Retourne le cookie _fbc (Click ID cookie, créé par fbclid).
     * Si absent, tente de le générer à partir de fbclid dans l'URL.
     * @returns {string}
     */
    getFbc() {
        var fbc = this.getCookie('_fbc');
        if (fbc) return fbc;

        // Tenter de construire _fbc depuis fbclid dans l'URL
        try {
            var params = new URLSearchParams(window.location.search);
            var fbclid = params.get('fbclid');
            if (fbclid) {
                return 'fb.1.' + Date.now() + '.' + fbclid;
            }
        } catch (e) {}
        return '';
    }

    /**
     * Retourne les données navigateur utiles pour le CAPI.
     * @returns {{fbp: string, fbc: string}}
     */
    getBrowserData() {
        return {
            fbp: this.getFbp(),
            fbc: this.getFbc()
        };
    }

    /**
     * Track un événement sur les plateformes spécifiées.
     * @param {string} eventName Nom de l'événement
     * @param {Object} eventData Données de l'événement
     * @param {string[]} platforms Plateformes cibles
     * @param {Object} [options] Options supplémentaires (eventID, etc.)
     */
    track(eventName, eventData = {}, platforms = ['facebook'], options = {}) {
        if (!this.isReady) {
            this.eventQueue.push({ eventName, eventData, platforms, options });
            return;
        }
        platforms.forEach(platform => {
            try {
                switch (platform) {
                    case 'facebook': this.trackFacebook(eventName, eventData, options); break;
                    case 'googleAnalytics': this.trackGoogleAnalytics(eventName, eventData); break;
                    case 'tiktok': this.trackTikTok(eventName, eventData); break;
                }
            } catch (error) {}
        });
    }
 
    /**
     * Envoie un événement au Pixel Facebook.
     * Supporte l'eventID pour la déduplication avec le CAPI.
     */
    trackFacebook(eventName, eventData, options = {}) {
        if (!this.config.facebook.enabled) return;
        try {
            if (typeof fbq === 'function') {
                var standardEvents = ['PageView', 'Purchase', 'Lead', 'InitiateCheckout', 'ViewContent', 'CompleteRegistration', 'AddToCart', 'AddPaymentInfo', 'Search'];
                var fbOptions = {};

                // Ajouter l'eventID pour la déduplication
                if (options.eventID) {
                    fbOptions.eventID = options.eventID;
                }

                if (standardEvents.includes(eventName)) {
                    if (Object.keys(fbOptions).length > 0) {
                        fbq('track', eventName, eventData, fbOptions);
                    } else {
                        fbq('track', eventName, eventData);
                    }
                } else {
                    if (Object.keys(fbOptions).length > 0) {
                        fbq('trackCustom', eventName, eventData, fbOptions);
                    } else {
                        fbq('trackCustom', eventName, eventData);
                    }
                }
            } else {
                // Fallback via image uniquement si fbq indisponible
                this.trackFacebookViaImage(eventName, eventData, options);
            }
        } catch (error) {
            this.trackFacebookViaImage(eventName, eventData, options);
        }
    }

    trackFacebookViaImage(eventName, eventData, options = {}) {
        this.config.facebook.pixels.forEach(pixelId => {
            try {
                var params = new URLSearchParams({
                    id: pixelId,
                    ev: eventName,
                    noscript: '1',
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

    processQueue() {
        while (this.eventQueue.length > 0) {
            var event = this.eventQueue.shift();
            this.track(event.eventName, event.eventData, event.platforms, event.options || {});
        }
    }

    addFacebookPixel(pixelId) {
        if (!this.config.facebook.pixels.includes(pixelId)) {
            this.config.facebook.pixels.push(pixelId);
            
            // Initialiser seulement si pas déjà fait
            if (typeof fbq === 'function') {
                if (!window.fbInitializedPixels) {
                    window.fbInitializedPixels = [];
                }
                if (!window.fbInitializedPixels.includes(pixelId)) {
                    fbq('init', pixelId);
                    window.fbInitializedPixels.push(pixelId);
                }
            }
        }
    }

    removeFacebookPixel(pixelId) {
        var index = this.config.facebook.pixels.indexOf(pixelId);
        if (index > -1) this.config.facebook.pixels.splice(index, 1);
    }
}

// Créer l'instance TrackingManager seulement si elle n'existe pas déjà
if (!window.trackingManager) {
    window.trackingManager = new TrackingManager(window.trackingManagerConfig || {});
}

// Fonction globale pour tracking (avec support eventID via options)
if (!window.trackEvent) {
    window.trackEvent = function(eventName, eventData, platforms, options) {
        if (!platforms) platforms = ['facebook'];
        if (!options) options = {};
        if (window.trackingManager) {
            window.trackingManager.track(eventName, eventData, platforms, options);
        }
    };
}
