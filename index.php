<?php
session_start();
require 'vendor/autoload.php';

use src\Connectbd;
use src\Product;
use src\Country;

$cnx = Connectbd::getConnection();
$productManager = new Product($cnx);
$countryManager = new Country($cnx);


if (!isset($_GET['id'])) {
    $product = $productManager->getRandomProduct();
    $productId = intval($product['product_id']);
} else {
    $productId = intval($_GET['id']);
}

if (isset($_SESSION['order_message'])) {
    $order_message = $_SESSION['order_message'];
    unset($_SESSION['order_message']);
}

$product = $productManager->getProducts($productId);
$productCountries = $productManager->getProductCountries($productId);

if (!$product) {
    header('Location: error.php?code=404');
    exit;
}

$characteristics = $productManager->getProductCharacteristics($productId);
$videos = $productManager->getProductVideos($productId);
$packs = $productManager->getProductPacks($productId);

// Convertit un code pays (FR, US...) en entité drapeau HTML
function countryCodeToFlagEntity($code)
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return '';
    }
    $first = 127397 + ord($code[0]);
    $second = 127397 + ord($code[1]);
    return '&#' . $first . ';&#' . $second . ';';
}

function countryCurrencyInfo($code, $name = '')
{
    $normalizedCode = strtoupper(trim((string) $code));
    $normalizedName = function_exists('mb_strtolower')
        ? mb_strtolower(trim((string) $name), 'UTF-8')
        : strtolower(trim((string) $name));

    if ($normalizedCode === 'GN' || strpos($normalizedName, 'guin') !== false) {
        return [
            'code' => 'GNF',
            'label' => 'GNF',
        ];
    }

    return [
        'code' => 'XOF',
        'label' => 'FCFA',
    ];
}

// Récupérer le prix du pays (à partir de la première association)
$selectedCountryId = isset($_GET['country']) ? intval($_GET['country']) : null;
$displayPrice = 0;
$displayCurrencyInfo = [
    'code' => 'XOF',
    'label' => 'FCFA',
];

if (!empty($productCountries)) {
    $selectedCountry = null;
    foreach ($productCountries as $ctry) {
        if ($selectedCountryId !== null && (int)$ctry['id'] === $selectedCountryId) {
            $selectedCountry = $ctry;
            break;
        }
    }
    if (!$selectedCountry) {
        foreach ($productCountries as $ctry) {
            $currencyInfo = countryCurrencyInfo($ctry['code'] ?? '', $ctry['name'] ?? '');
            if (($currencyInfo['code'] ?? '') !== 'GNF') {
                $selectedCountry = $ctry;
                break;
            }
        }
        if (!$selectedCountry) {
            $selectedCountry = $productCountries[0];
        }
    }

    $displayPrice = (int)($selectedCountry['selling_price'] ?? 0);
    $selectedCountryId = (int)($selectedCountry['id'] ?? 0);
    $displayCurrencyInfo = countryCurrencyInfo($selectedCountry['code'] ?? '', $selectedCountry['name'] ?? '');
}

$displayTitle = $product['name'];
$displayDescription = $product['description'];

$facebookPixelIds = ['1536994954069676', '1373481401089526'];
$envPath = __DIR__ . '/src/.env';
if (is_file($envPath)) {
    $envConfig = parse_ini_file($envPath, true);
    if (!empty($envConfig['facebook_pixels']) && is_array($envConfig['facebook_pixels'])) {
        $envFacebookPixelIds = array_values(array_filter(
            array_map('strval', array_keys($envConfig['facebook_pixels'])),
            static fn($pixelId) => preg_match('/^\d+$/', $pixelId)
        ));
        if (!empty($envFacebookPixelIds)) {
            $facebookPixelIds = $envFacebookPixelIds;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayTitle); ?></title>
    <meta property="og:title" content="<?= htmlspecialchars($displayTitle); ?>" />
    <meta property="og:description"
        content="<?= htmlspecialchars(substr(strip_tags($displayDescription), 0, 150)); ?>..." />
    <meta property="og:image" content="https://luxemarket.click/uploads/main/<?= $product['image']; ?>" />
    <meta property="og:url" content="https://luxemarket.click/index.php?id=<?= $product['id'] ?>" />
    <meta property="og:type" content="product" />
    <meta property="og:site_name" content="LUXEMARKET" />
    <meta property="og:locale" content="fr_FR" />

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= htmlspecialchars($displayTitle); ?>" />
    <meta name="twitter:description"
        content="<?= htmlspecialchars(substr(strip_tags($displayDescription), 0, 150)); ?>..." />
    <meta name="twitter:image" content="https://luxemarket.click/uploads/main/<?= $product['image']; ?>" />
    <meta name="twitter:site" content="@luxemarketclick" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="./assets/css/product-store.css">
</head>
 
<body class="page-storefront product-page">

    <header class="store-header">
        <div class="container">
            <a href="/" class="logo" aria-label="Accueil">
                <img src="assets/images/logo.jpg" alt="LUXEMARKET">
            </a>
            <button type="button" class="btn-order-nav commander-btn" onclick="location.href='#product_details'">
                <i class='bx bx-cart-alt'></i>
                Commander
            </button>
        </div>
    </header>

    <main class="store-main">
        <div class="store-grid">
            <div class="store-info" id="product_details">
                <div class="store-card">
                    <div class="store-card-body">
                        <h1 class="store-title"><?= htmlspecialchars($displayTitle); ?></h1>
                        <div class="store-price-wrap">
                            <span class="store-price-label">Prix pour votre pays</span>
                            <p class="store-price">
                                <span id="display-price" data-price="<?= (int)$displayPrice ?>"><?= number_format($displayPrice, 0, ',', ' '); ?></span>
                                <span class="store-currency" data-currency-code="<?= htmlspecialchars($displayCurrencyInfo['code'], ENT_QUOTES); ?>"><?= htmlspecialchars($displayCurrencyInfo['label']); ?></span>
                            </p>
                        </div>
                        <p class="store-form-title">Vos coordonnées</p>
                        <form class="express-checkout-form store-form" method="POST" action="management/orders/save.php">
                            <div class="store-field">
                                <label for="client_name">Nom complet</label>
                                <input type="text" id="client_name" name="client_name" class="store-input" placeholder="Jean Dupont" required>
                            </div>
                            <div class="store-field">
                                <label for="client_country_select">Contact</label>
                                <div class="phone-input-wrapper">
                                    <select name="client_country" id="client_country_select" class="form-control-country" required>
                                        <?php foreach ($productCountries as $ctry): ?>
                                            <?php
                                            $flag = countryCodeToFlagEntity($ctry['code'] ?? '');
                                            $isSelected = ($selectedCountryId !== null && (int)$ctry['id'] === $selectedCountryId);
                                            $currencyInfo = countryCurrencyInfo($ctry['code'] ?? '', $ctry['name'] ?? '');
                                            ?>
                                            <option value="<?= (int)$ctry['id']; ?>" data-price="<?= (int)($ctry['selling_price'] ?? 0); ?>" data-country-code="<?= htmlspecialchars(strtoupper(trim($ctry['code'] ?? '')), ENT_QUOTES); ?>" data-country-name="<?= htmlspecialchars($ctry['name'] ?? '', ENT_QUOTES); ?>" data-currency-code="<?= htmlspecialchars($currencyInfo['code'], ENT_QUOTES); ?>" data-currency-label="<?= htmlspecialchars($currencyInfo['label'], ENT_QUOTES); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                                                <?= $flag ? $flag . ' ' : '' ?><?= htmlspecialchars($ctry['phone_code'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="tel" id="client_phone" name="client_phone" class="form-control-custom" placeholder="Numéro" required>
                                </div>
                                <small class="form-hint">Le prix ci-dessus s’adapte au pays sélectionné.</small>
                            </div>
                            <div class="store-field">
                                <label for="client_adress">Adresse</label>
                                <input type="text" id="client_adress" name="client_adress" class="form-control-custom" placeholder="Ville, Quartier" required>
                            </div>
                            <div class="store-field">
                                <label for="client_note">Note</label>
                                <textarea id="client_note" name="client_note" class="form-control-custom" rows="2" placeholder="Note éventuelle"></textarea>
                            </div>

                        <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                        <input type="hidden" name="valider" value="commander">
                        <!-- Champs CAPI : event_id et cookies Facebook pour la déduplication -->
                        <input type="hidden" name="fb_event_id" id="fb_event_id" value="">
                        <input type="hidden" name="fb_fbp" id="fb_fbp" value="">
                        <input type="hidden" name="fb_fbc" id="fb_fbc" value="">
                    <div class="modal-footer-custom">
                        <button type="submit" class="btn-submit-order">
                            <i class='bx bx-check-circle'></i>
                            <span>Valider la commande</span>
                        </button>
                    </div>
                </form>
                    </div>
                </div>
            </div>

            <div class="store-gallery">
                <div class="swiper productSwiperMain">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <img src="uploads/main/<?= htmlspecialchars($product['image']); ?>" alt="<?= htmlspecialchars($displayTitle); ?>">
                        </div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if (!empty($product['carousel' . $i])): ?>
                                <div class="swiper-slide">
                                    <img src="uploads/carousel/<?= htmlspecialchars($product['carousel' . $i]); ?>" alt="">
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="swiper productSwiperThumbs">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <img src="uploads/main/<?= htmlspecialchars($product['image']); ?>" alt="">
                        </div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if (!empty($product['carousel' . $i])): ?>
                                <div class="swiper-slide">
                                    <img src="uploads/carousel/<?= htmlspecialchars($product['carousel' . $i]); ?>" alt="">
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="store-description">
            <?= $displayDescription; ?>
        </div>

        <div class="toast-container store-toast-container">
                <div id="liveToast" class="toast align-items-center text-white border-0" role="alert"
                    aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                    <div class="d-flex">
                        <div id="toastMessage" class="toast-body">
                            <?= isset($order_message) ? htmlspecialchars($order_message) : ''; ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                            aria-label="Close"></button>
                    </div>
                </div>
            </div>
    </main>

    <footer class="store-footer">
        <div class="container">
            <img src="assets/images/logo.jpg" alt="LUXEMARKET">
            <p><strong>Tous les droits réservés © 2025</strong></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        window.trackingManagerConfig = {
            facebook: {
                pixels: <?= json_encode($facebookPixelIds, JSON_UNESCAPED_SLASHES); ?>
            }
        };
    </script>
    <script src="assets/js/tracking-manager.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/index.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var thumbs = document.querySelector('.productSwiperThumbs');
            var main = document.querySelector('.productSwiperMain');
            if (thumbs && main) {
                var thumbsSwiper = new Swiper('.productSwiperThumbs', {
                    spaceBetween: 8,
                    slidesPerView: 4,
                    freeMode: true,
                    watchSlidesProgress: true,
                    breakpoints: { 480: { slidesPerView: 5 } }
                });
                new Swiper('.productSwiperMain', {
                    spaceBetween: 0,
                    thumbs: { swiper: thumbsSwiper },
                    loop: false
                });
            }
        });
    </script>
    <script>
        (function() {
            function safeParseInt(value) {
                const parsed = parseInt(value, 10);
                return Number.isNaN(parsed) ? 0 : parsed;
            }

            function getCookie(name) {
                const cookies = document.cookie ? document.cookie.split('; ') : [];
                for (let i = 0; i < cookies.length; i += 1) {
                    const parts = cookies[i].split('=');
                    const key = decodeURIComponent(parts.shift());
                    if (key === name) {
                        return decodeURIComponent(parts.join('='));
                    }
                }
                return '';
            }

            function setCookie(name, value, maxAgeSeconds) {
                let maxAge = '';
                if (maxAgeSeconds) {
                    maxAge = '; max-age=' + Math.max(0, Math.floor(maxAgeSeconds));
                }
                document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + maxAge + '; path=/';
            }

            function readOrderState(key) {
                let stored = null;
                try {
                    stored = localStorage.getItem(key);
                } catch (error) {
                    stored = null;
                }

                if (!stored) {
                    return { count: 0, expiresAt: null };
                }

                try {
                    const parsed = JSON.parse(stored);
                    if (parsed && typeof parsed === 'object') {
                        return {
                            count: safeParseInt(parsed.count),
                            expiresAt: typeof parsed.expiresAt === 'number' ? parsed.expiresAt : null,
                            isLegacy: typeof parsed.expiresAt !== 'number'
                        };
                    }
                    return { count: safeParseInt(parsed), expiresAt: null, isLegacy: true };
                } catch (error) {
                    return { count: safeParseInt(stored), expiresAt: null, isLegacy: true };
                }
            }

            function getOrderCount(id, windowMs) {
                const key = 'order_limit_' + id;
                const state = readOrderState(key);
                if (state.expiresAt && Date.now() > state.expiresAt) {
                    try {
                        localStorage.removeItem(key);
                    } catch (error) {}
                }
                if (!state.expiresAt && state.count > 0 && state.isLegacy) {
                    const seededExpiresAt = Date.now() + windowMs;
                    try {
                        localStorage.setItem(key, JSON.stringify({
                            count: state.count,
                            expiresAt: seededExpiresAt
                        }));
                    } catch (error) {}
                    return Math.max(state.count, safeParseInt(getCookie(key)));
                }

                const localCount = state.expiresAt && Date.now() > state.expiresAt ? 0 : state.count;
                const cookieCount = safeParseInt(getCookie(key));
                return Math.max(localCount, cookieCount);
            }

            function setOrderCount(id, count, windowMs) {
                const key = 'order_limit_' + id;
                const expiresAt = Date.now() + windowMs;
                try {
                    localStorage.setItem(key, JSON.stringify({
                        count: count,
                        expiresAt: expiresAt
                    }));
                } catch (error) {}
                setCookie(key, String(count), windowMs / 1000);
            }

            function getBlockedProducts(windowMs) {
                try {
                    const stored = localStorage.getItem('order_limit_blocked_products');
                    if (!stored) return [];
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed)) {
                        return parsed;
                    }
                    if (!parsed || typeof parsed !== 'object') return [];
                    if (parsed.expiresAt && Date.now() > parsed.expiresAt) {
                        localStorage.removeItem('order_limit_blocked_products');
                        return [];
                    }
                    return Array.isArray(parsed.ids) ? parsed.ids : [];
                } catch (error) {
                    return [];
                }
            }

            function setBlockedProducts(list, windowMs) {
                try {
                    localStorage.setItem('order_limit_blocked_products', JSON.stringify({
                        ids: list,
                        expiresAt: Date.now() + windowMs
                    }));
                } catch (error) {}
            }

            function markBlockedProduct(id, windowMs) {
                const blocked = getBlockedProducts(windowMs);
                const normalizedId = String(id);
                if (blocked.indexOf(normalizedId) === -1) {
                    blocked.push(normalizedId);
                    setBlockedProducts(blocked, windowMs);
                }
            }

            function isProductBlocked(id, windowMs) {
                const blocked = getBlockedProducts(windowMs);
                return blocked.indexOf(String(id)) !== -1;
            }

            function applyLimitState(id, limit, windowMs) {
                const count = getOrderCount(id, windowMs);
                if (count >= limit) {
                    markBlockedProduct(id, windowMs);
                }

                if (isProductBlocked(id, windowMs)) {
                    document.querySelectorAll('.commander-btn, .btn-submit-order').forEach(function(btn) {
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        btn.classList.add('btn-disabled-order-limit');
                        const labelEl = btn.querySelector('span');
                        if (labelEl) {
                            labelEl.textContent = 'Limite atteinte, l’assistance vous contactera.';
                        } else {
                            btn.textContent = 'Limite atteinte, l’assistance vous contactera.';
                        }
                    });
                }
            }

            window.createOrderLimit = function(productId, options) {
                const limit = options && options.limit ? options.limit : 2;
                const doubleClickGuardMs = options && options.doubleClickGuardMs ? options.doubleClickGuardMs : 2500;
                const windowMs = options && options.windowMs ? options.windowMs : 48 * 60 * 60 * 1000;
                let lastSubmitAt = 0;

                function canSubmit() {
                    const now = Date.now();
                    if (now - lastSubmitAt < doubleClickGuardMs) {
                        return false;
                    }
                    lastSubmitAt = now;

                    if (getOrderCount(productId, windowMs) >= limit || isProductBlocked(productId, windowMs)) {
                        applyLimitState(productId, limit, windowMs);
                        return false;
                    }
                    return true;
                }

                function registerSubmit() {
                    const nextCount = getOrderCount(productId, windowMs) + 1;
                    setOrderCount(productId, nextCount, windowMs);
                    if (nextCount >= limit) {
                        markBlockedProduct(productId, windowMs);
                    }
                    applyLimitState(productId, limit, windowMs);
                }

                return {
                    applyLimitState: function() {
                        applyLimitState(productId, limit, windowMs);
                    },
                    canSubmit: canSubmit,
                    registerSubmit: registerSubmit
                };
            };
        })();
    </script>

    <script>
        function getCurrentCurrencyInfo() {
            var countrySelect = document.getElementById('client_country_select');
            var fallback = {
                code: 'XOF',
                label: 'FCFA'
            };

            if (!countrySelect || !countrySelect.options || countrySelect.selectedIndex < 0) {
                return fallback;
            }

            var opt = countrySelect.options[countrySelect.selectedIndex];
            if (!opt) {
                return fallback;
            }

            return {
                code: opt.getAttribute('data-currency-code') || fallback.code,
                label: opt.getAttribute('data-currency-label') || fallback.label
            };
        }

        function getCurrentCurrencyCode() {
            return getCurrentCurrencyInfo().code;
        }

        /**
         * Attend que le TrackingManager soit prêt, puis envoie l'événement.
         * Supporte un 4ème argument `options` pour passer eventID, etc.
         */
        function trackWhenReady(eventName, eventData, attemptsOrOptions, maybeOptions) {
            var options = {};
            var attempts;

            // Déterminer les arguments : trackWhenReady(name, data, options) ou trackWhenReady(name, data, attempts, options)
            if (typeof attemptsOrOptions === 'object' && attemptsOrOptions !== null && !Array.isArray(attemptsOrOptions)) {
                options = attemptsOrOptions;
                attempts = undefined;
            } else {
                attempts = attemptsOrOptions;
                if (typeof maybeOptions === 'object' && maybeOptions !== null) {
                    options = maybeOptions;
                }
            }

            var defaultAttemptsByEvent = {
                Purchase: 40,
                InitiateCheckout: 30,
                ViewContent: 20
            };
            var fallbackAttempts = 20;
            var remaining = typeof attempts === 'number'
                ? attempts
                : (defaultAttemptsByEvent[eventName] || fallbackAttempts);

            if (typeof trackEvent === 'function' && (!window.trackingManager || window.trackingManager.isReady)) {
                trackEvent(eventName, eventData, ['facebook'], options);
                return;
            }
            if (remaining <= 0) return;
            setTimeout(function() {
                trackWhenReady(eventName, eventData, remaining - 1, options);
            }, 200);
        }

        document.addEventListener('DOMContentLoaded', function() {
            var countrySelect = document.getElementById('client_country_select');
            var displayPriceEl = document.getElementById('display-price');
            var displayCurrencyEl = document.querySelector('.store-currency');
            if (countrySelect && displayPriceEl) {
                function formatPrice(n) {
                    return Number(n).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
                }
                function updateCurrencyFromCountry() {
                    if (!displayCurrencyEl) return;
                    var currencyInfo = getCurrentCurrencyInfo();
                    displayCurrencyEl.textContent = currencyInfo.label;
                    displayCurrencyEl.setAttribute('data-currency-code', currencyInfo.code);
                }
                function updatePriceFromCountry() {
                    var opt = countrySelect.options[countrySelect.selectedIndex];
                    if (!opt) return;
                    var price = parseInt(opt.getAttribute('data-price'), 10) || 0;
                    displayPriceEl.textContent = formatPrice(price);
                    displayPriceEl.setAttribute('data-price', price);
                    updateCurrencyFromCountry();
                    var baseUrl = window.location.pathname + '?id=<?= (int)$product["id"] ?>';
                    var countryId = opt.value;
                    var newUrl = baseUrl + (countryId ? '&country=' + encodeURIComponent(countryId) : '');
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', newUrl);
                    }
                }
                updateCurrencyFromCountry();
                countrySelect.addEventListener('change', updatePriceFromCountry);
            }

            // ──────────────────────────────────────────────
            //  ÉVÉNEMENT STANDARD : ViewContent (remplace QualifiedVisit)
            //  Envoyé au chargement de la page produit
            // ──────────────────────────────────────────────
            trackWhenReady('ViewContent', {
                content_ids: ['<?= $product['id']; ?>'],
                content_name: '<?= htmlspecialchars($product['name'], ENT_QUOTES); ?>',
                content_type: 'product',
                value: <?= $displayPrice; ?>,
                currency: getCurrentCurrencyCode()
            });

            var productId = '<?= $product['id']; ?>';
            var orderLimitApi = window.createOrderLimit(productId, {
                limit: 3,
                doubleClickGuardMs: 2500,
                windowMs: 5 * 60 * 60 * 1000
            });
            orderLimitApi.applyLimitState();

            var orderForm = document.querySelector('.express-checkout-form');
            var formSubmitted = false;

            // ──────────────────────────────────────────────
            //  ÉVÉNEMENT STANDARD : InitiateCheckout
            //  Envoyé au premier focus sur un champ du formulaire
            // ──────────────────────────────────────────────
            var initiateCheckoutSent = false;
            if (orderForm) {
                var formFields = orderForm.querySelectorAll('input[type="text"], input[type="tel"], textarea');
                formFields.forEach(function(field) {
                    field.addEventListener('focus', function onFirstFocus() {
                        if (initiateCheckoutSent) return;
                        initiateCheckoutSent = true;

                        var currentPrice = (displayPriceEl && parseInt(displayPriceEl.getAttribute('data-price'), 10)) || <?= (int)$displayPrice; ?>;
                        trackWhenReady('InitiateCheckout', {
                            content_ids: ['<?= $product['id']; ?>'],
                            content_type: 'product',
                            contents: [{
                                id: '<?= $product['id']; ?>',
                                quantity: 1,
                                item_price: currentPrice
                            }],
                            currency: getCurrentCurrencyCode(),
                            num_items: 1,
                            value: currentPrice
                        });
                    });
                });

                // ──────────────────────────────────────────────
                //  SOUMISSION DU FORMULAIRE + Purchase
                // ──────────────────────────────────────────────
                orderForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (formSubmitted) return;
                    if (!orderLimitApi.canSubmit()) return;

                    orderLimitApi.registerSubmit();
                    formSubmitted = true;

                    // Disable the button immediately
                    var submitBtn = orderForm.querySelector('.btn-submit-order');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.style.pointerEvents = 'none';
                        var span = submitBtn.querySelector('span');
                        if (span) {
                            span.innerHTML = 'Traitement...';
                        }
                    }

                    // ── Générer un event_id unique pour la déduplication Pixel ↔ CAPI ──
                    var eventId = '';
                    if (window.trackingManager && typeof window.trackingManager.generateEventId === 'function') {
                        eventId = window.trackingManager.generateEventId();
                    } else {
                        eventId = Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 10);
                    }

                    // ── Injecter event_id + cookies Facebook dans le formulaire ──
                    var fbEventIdField = document.getElementById('fb_event_id');
                    var fbFbpField = document.getElementById('fb_fbp');
                    var fbFbcField = document.getElementById('fb_fbc');

                    if (fbEventIdField) fbEventIdField.value = eventId;

                    if (window.trackingManager) {
                        var browserData = window.trackingManager.getBrowserData();
                        if (fbFbpField) fbFbpField.value = browserData.fbp || '';
                        if (fbFbcField) fbFbcField.value = browserData.fbc || '';
                    }

                    // ── Construire le payload Purchase ──
                    var packIdInput = document.getElementById('selectedPackId');
                    var packSelect = document.getElementById('packSelection');
                    var selectedPackId = packIdInput ? (packIdInput.value || '') : '';
                    var purchasePayload = {
                        currency: getCurrentCurrencyCode()
                    };

                    if (selectedPackId && packSelect) {
                        var option = Array.from(packSelect.options).find(function(opt) {
                            return opt.value === selectedPackId;
                        });
                        if (option) {
                            var packPrice = parseInt(option.dataset.price, 10) || 0;
                            var packQty = parseInt(option.dataset.quantity, 10) || 1;

                            purchasePayload.content_ids = [selectedPackId];
                            purchasePayload.content_type = 'product';
                            purchasePayload.contents = [{
                                id: selectedPackId,
                                quantity: packQty,
                                item_price: packPrice
                            }];
                            purchasePayload.num_items = packQty;
                            purchasePayload.value = packPrice;
                        }
                    }

                    if (!purchasePayload.content_ids) {
                        var currentPrice = (displayPriceEl && parseInt(displayPriceEl.getAttribute('data-price'), 10)) || <?= (int)$displayPrice; ?>;
                        purchasePayload.content_ids = ['<?= $product['id']; ?>'];
                        purchasePayload.content_type = 'product';
                        purchasePayload.contents = [{
                            id: '<?= $product['id']; ?>',
                            quantity: 1,
                            item_price: currentPrice
                        }];
                        purchasePayload.num_items = 1;
                        purchasePayload.value = currentPrice;
                    }

                    // ── Envoyer Purchase avec eventID pour la déduplication ──
                    trackWhenReady('Purchase', purchasePayload, { eventID: eventId });

                    // ── Soumettre le formulaire au serveur ──
                    var submitUrl = orderForm.getAttribute('action') || window.location.href;
                    var formData = new FormData(orderForm);

                    var sendRequest = function() {
                        fetch(submitUrl, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(function(response) {
                                if (response.redirected) {
                                    window.location.href = response.url;
                                    return;
                                }
                                return response.text().then(function() {
                                    window.location.href = response.url || window.location.href;
                                });
                            })
                            .catch(function() {
                                orderForm.submit();
                            });
                    };

                    // Laisser le temps au Pixel d'envoyer le Purchase avant la navigation
                    setTimeout(sendRequest, 400);
                });
            }
        });
    </script>
    
</body>

</html>
