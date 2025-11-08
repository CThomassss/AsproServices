<?php
// includes/promotions.php
// Connect to MySQL using admin/config.php constants and echo the promotions HTML

require_once __DIR__ . '/../admin/config.php';

function fetch_promotions_from_db()
{
    $mysqli = @new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset('utf8mb4');

    // Get active promotions (optional: filter by validUntil but keep all for now)
    $today = date('Y-m-d');
    // Note: do NOT request `document` here because older DB schemas may not have that column.
    $sql = "SELECT id, title, discount, description, validUntil, imageUrl, pdfUrl FROM promotions ORDER BY id DESC";
    $res = $mysqli->query($sql);
    if (!$res) {
        $mysqli->close();
        return null;
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $mysqli->close();
    return $rows;
}

function fetch_promotions_from_json()
{
    if (!file_exists(PROMOTIONS_JSON)) {
        return [];
    }
    $json = @file_get_contents(PROMOTIONS_JSON);
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

$promotions = fetch_promotions_from_db();
// If DB is unavailable (null) or returns no rows (empty array), fall back to JSON to ease local development
if ($promotions === null || empty($promotions)) {
    $promotions = fetch_promotions_from_json();
}

// Helper to build an image URL
function promotion_image_url($imageUrl)
{
    // Robust normalization: decode HTML entities and URL-encoding, strip query strings,
    // map various stored formats to the local `public/promotions/` folder when possible.
    if (empty($imageUrl)) {
        return rtrim(SITE_URL, '/') . '/public/promotions/placeholder.jpg';
    }

    // Decode HTML entities and percent-encoding
    $decoded = html_entity_decode($imageUrl);
    $decoded = urldecode($decoded);

    // Remove any query string parts
    $parts = preg_split('/[?#]/', $decoded);
    $path = trim($parts[0]);

    // If it's an absolute HTTP URL, return as-is
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // If path contains an encoded folder like /AS-PRO-SERVICE/promotions/... or /promotions/...
    // extract the basename and check local public/promotions
    $basename = basename($path);
    $localCandidate = __DIR__ . '/../public/promotions/' . $basename;
    if (file_exists($localCandidate)) {
        return rtrim(SITE_URL, '/') . '/public/promotions/' . $basename;
    }

    // If path already looks like an absolute site path (/promotions/...), try to map it
    if (strpos($path, '/promotions/') === 0 || strpos($path, '/public/promotions/') === 0) {
        // make absolute URL based on SITE_URL
        return rtrim(SITE_URL, '/') . $path;
    }

    // Last resort: assume the stored value is a filename and map to public/promotions
    return rtrim(SITE_URL, '/') . '/public/promotions/' . ltrim($path, '/');
}

// Helper to build a normalized PDF/document URL (handles filenames with spaces)
function promotion_pdf_url($path)
{
    if (empty($path)) return '';
    $decoded = html_entity_decode($path);
    $decoded = urldecode($decoded);
    $parts = preg_split('/[?#]/', $decoded);
    $p = trim($parts[0]);

    // If it's an absolute HTTP URL, return as-is
    if (preg_match('#^https?://#i', $p)) return $p;

    // If it looks like a site-relative path (/promotions/... or /public/promotions/...), map to SITE_URL
    if (strpos($p, '/promotions/') === 0 || strpos($p, '/public/promotions/') === 0) {
        // ensure we encode only the basename to preserve slashes
        $dirname = rtrim(dirname($p), '/');
        $basename = basename($p);
        return rtrim(SITE_URL, '/') . $dirname . '/' . rawurlencode($basename);
    }

    // If the stored value already contains 'public/promotions' (relative), normalize
    if (strpos($p, 'public/promotions/') !== false) {
        $basename = basename($p);
        return rtrim(SITE_URL, '/') . '/public/promotions/' . rawurlencode($basename);
    }

    // Last resort: assume it's a filename in public/promotions
    $basename = basename($p);
    return rtrim(SITE_URL, '/') . '/public/promotions/' . rawurlencode($basename);
}

// Render the promotions section markup
?>
<section class="relative overflow-hidden bg-slate-950 py-24 text-white" id="promotions">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(142,174,210,0.1),_transparent_50%)]"></div>
    <div class="relative mx-auto max-w-6xl px-4">
        <div class="flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
            <div class="space-y-4">
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-accent">Offres spéciales</p>
                <div class="flex items-center gap-3">
                    <h2 class="text-3xl font-semibold tracking-tight text-white md:text-4xl">Promotions &amp; offres du moment</h2>
                </div>
            </div>
            <!-- Arrow buttons placed to the right of the header on md+ screens to avoid overlapping the title -->
            <div class="hidden md:flex items-center ml-8 gap-6">
                <button id="promotions-prev-top" aria-label="Précédent" class="inline-flex items-center justify-center rounded-full bg-white text-slate-900 p-2 shadow-md border border-white/20 w-12 h-12 text-lg">
                    ‹
                </button>
                <button id="promotions-next-top" aria-label="Suivant" class="inline-flex items-center justify-center rounded-full bg-white text-slate-900 p-2 shadow-md border border-white/20 w-12 h-12 text-lg">
                    ›
                </button>
            </div>
        </div>
        </div>

        <style>
            /* Carousel: responsive layout and scrollbar hiding
               - .promotions-wrap centers the carousel
               - #promotions-carousel will be paged via JS (overflow hidden)
            */
            .promotions-wrap{position:relative;max-width:1300px;margin-left:auto;margin-right:auto}
            /* Allow native horizontal scrolling (show scrollbar) so users can drag/scroll the row */
            #promotions-carousel{display:flex;gap:2rem;overflow-x:auto;scroll-behavior:smooth;padding-bottom:1rem;touch-action:pan-y;-webkit-overflow-scrolling:touch}
            /* Desktop large: show 3 items per view (two gaps = 4rem) */
            #promotions-carousel article{flex:0 0 calc((100% - 4rem) / 3);min-width:220px}
            /* Medium screens: show 2 items */
            @media (max-width:1199px){#promotions-carousel article{flex:0 0 calc((100% - 2rem) / 2);min-width:260px}}

            /* Top arrow buttons (next/prev) placed beside the title — larger and boxed for better discoverability */
            #promotions-prev-top, #promotions-next-top{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#ffffff;color:#0f172a;border:1px solid rgba(255,255,255,0.15);box-shadow:0 6px 18px rgba(2,6,23,0.5);cursor:pointer}
            #promotions-prev-top{width:48px;height:48px;font-size:1.1rem;padding:0}
            #promotions-next-top{width:48px;height:48px;font-size:1.1rem;padding:0}


            /* On narrower screens show one item and hide arrows (mobile touch swipes are fine) */
            @media (max-width:900px){#promotions-carousel article{flex:0 0 100%;min-width:260px} #promotions-prev-top,#promotions-next-top{display:none}}
        </style>

        <div class="mt-14 promotions-wrap">
            <?php if (empty($promotions)): ?>
                <div class="text-white/70">Aucune promotion disponible pour le moment.</div>
            <?php else: ?>
                <div id="promotions-carousel">
                <?php foreach ($promotions as $p):
                    // optional: skip expired promotions
                    $validUntil = !empty($p['validUntil']) ? $p['validUntil'] : null;
                    if ($validUntil && strtotime($validUntil) < strtotime(date('Y-m-d'))) {
                        // skip expired
                        continue;
                    }
                    $img = promotion_image_url($p['imageUrl'] ?? '');
                    $title = htmlspecialchars($p['title'] ?? 'Promotion');
                    $discount = htmlspecialchars($p['discount'] ?? '');
                    $desc = nl2br(htmlspecialchars($p['description'] ?? ''));
                    $validLabel = $validUntil ? date('j F Y', strtotime($validUntil)) : '';
                ?>
                <article class="group flex flex-col overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-lg backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-accent/60 hover:shadow-2xl">
                    <div class="relative aspect-[16/9] w-full overflow-hidden">
                        <img alt="<?= $title ?>" class="object-cover transition duration-700 group-hover:scale-110" data-nimg="fill" decoding="async" loading="lazy" sizes="100vw" src="<?= $img ?>" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent"/>
                        <?php if ($discount): ?>
                            <span class="absolute left-5 top-5 rounded-full bg-accent px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-900 shadow-lg"><?= $discount ?></span>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-slate-950/0"></div>
                    </div>
                    <div class="flex flex-1 flex-col gap-4 p-8 text-white">
                        <h3 class="text-2xl font-semibold leading-tight"><?= $title ?></h3>
                        <div class="text-sm leading-relaxed text-white/70"><?= $desc ?></div>
                        <?php
                            // Detect a PDF URL in several possible fields (document, pdfUrl, or imageUrl pointing to a PDF)
                            $pdfUrl = '';
                            if (!empty($p['document'])) {
                                $pdfUrl = promotion_pdf_url($p['document']);
                            } elseif (!empty($p['pdfUrl'])) {
                                $pdfUrl = promotion_pdf_url($p['pdfUrl']);
                            } elseif (!empty($p['imageUrl']) && preg_match('/\.pdf($|[?#])/i', $p['imageUrl'])) {
                                $pdfUrl = promotion_pdf_url($p['imageUrl']);
                            }

                            // If still empty, try auto-discovery: look for a PDF file in public/promotions
                            // whose filename contains the promotion title (normalized) or the id.
                            if (empty($pdfUrl)) {
                                $found = '';
                                $titleForMatch = '';
                                if (!empty($p['title'])) {
                                    $titleForMatch = preg_replace('/[^a-z0-9]+/i', '-', strtolower($p['title']));
                                }
                                $idForMatch = !empty($p['id']) ? (string)$p['id'] : '';
                                $promDir = realpath(__DIR__ . '/../public/promotions');
                                if ($promDir && is_dir($promDir)) {
                                    foreach (glob($promDir . '/*.pdf') as $file) {
                                        $bn = basename($file);
                                        $bnLower = strtolower($bn);
                                        if ($idForMatch && strpos($bnLower, $idForMatch) !== false) {
                                            $found = $bn;
                                            break;
                                        }
                                        if ($titleForMatch && strpos($bnLower, strtolower($titleForMatch)) !== false) {
                                            $found = $bn;
                                            break;
                                        }
                                        // also try normalized comparison without dashes
                                        if ($titleForMatch && strpos($bnLower, str_replace('-', '', strtolower($titleForMatch))) !== false) {
                                            $found = $bn;
                                            break;
                                        }
                                    }
                                }
                                if ($found) {
                                    $pdfUrl = rtrim(SITE_URL, '/') . '/public/promotions/' . rawurlencode($found);
                                }
                            }
                        ?>
                        <?php if (!empty($pdfUrl)): ?>
                            <div class="mt-3">
                                <a class="inline-block rounded bg-white/5 px-3 py-2 text-sm font-semibold text-accent hover:bg-white/10" href="<?= htmlspecialchars($pdfUrl) ?>" rel="noopener noreferrer" target="_blank">
                                    Télécharger le catalogue
                                    <span aria-hidden="true" class="ml-2">📥</span>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if ($validLabel): ?>
                            <p class="mt-auto text-xs font-semibold uppercase tracking-[0.3em] text-accent">Offre valable jusqu'au<br><?= $validLabel ?></p>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        </div>
                </div>

        <script>
            (function(){
                var container = document.getElementById('promotions-carousel');
                var btnPrevTop = document.getElementById('promotions-prev-top');
                var btnNextTop = document.getElementById('promotions-next-top');
                if (!container) return;

                var cards = Array.prototype.slice.call(container.querySelectorAll('article'));
                var itemCount = cards.length;

                // determine visible slots responsively: 1 on small screens, 2 on desktop
                function computeVisibleSlots() {
                    var w = window.innerWidth;
                    if (w <= 900) return 1;
                    if (w <= 1199) return 2;
                    return 3;
                }
                var visibleSlots = computeVisibleSlots();
                var pages = Math.max(1, Math.ceil(itemCount / visibleSlots));
                var currentPage = 0;

                // hide controls if not enough items
                function maybeHideControls() {
                    if (itemCount <= visibleSlots) {
                        [btnPrevTop, btnNextTop].forEach(function(b){ if (b) b.style.display = 'none'; });
                        return true;
                    }
                    [btnPrevTop, btnNextTop].forEach(function(b){ if (b) b.style.display = ''; });
                    return false;
                }
                if (maybeHideControls()) return;

                // pagination dots removed per user request

                function goToPage(pageIndex) {
                    // recalc sizes
                    visibleSlots = computeVisibleSlots();
                    pages = Math.max(1, Math.ceil(itemCount / visibleSlots));
                    pageIndex = Math.max(0, Math.min(pages - 1, pageIndex));

                    var gap = parseFloat(getComputedStyle(container).gap) || 32;
                    var card = cards[0];
                    var cardW = card ? card.getBoundingClientRect().width : container.clientWidth;
                    var pageWidth = Math.round((cardW * visibleSlots) + (gap * (visibleSlots - 1)));
                    var left = Math.round(pageIndex * pageWidth);
                    // use smooth scroll where supported
                    container.scrollTo({ left: left, behavior: 'smooth' });
                    currentPage = pageIndex;
                }

                function nextPage(){ goToPage((currentPage + 1) % pages); }
                function prevPage(){ goToPage((currentPage - 1 + pages) % pages); }

                // Scroll by N cards (n can be negative). Uses card width + gap so each click moves exactly one card.
                function scrollByCards(n) {
                    var gap = parseFloat(getComputedStyle(container).gap) || 32;
                    var card = cards[0];
                    if (!card) return;
                    var cardW = card.getBoundingClientRect().width;
                    var delta = Math.round((cardW + gap) * n);
                    var maxLeft = Math.max(0, container.scrollWidth - container.clientWidth);
                    var target = Math.round(container.scrollLeft + delta);
                    if (target < 0) target = 0;
                    if (target > maxLeft) target = maxLeft;
                    container.scrollTo({ left: target, behavior: 'smooth' });
                    // update currentPage approximation (optional)
                    var pageWidth = Math.round((cardW * visibleSlots) + (gap * (visibleSlots - 1)));
                    currentPage = Math.max(0, Math.min(pages - 1, Math.round(target / (pageWidth || 1))));
                }

                // wire top buttons only
                var prevButtons = [btnPrevTop].filter(Boolean);
                var nextButtons = [btnNextTop].filter(Boolean);
                prevButtons.forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); scrollByCards(-1); startTimer(); }); });
                nextButtons.forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); scrollByCards(1); startTimer(); }); });

                // keyboard navigation for accessibility
                container.addEventListener('keydown', function(e){
                    if (e.key === 'ArrowRight') { scrollByCards(1); startTimer(); }
                    if (e.key === 'ArrowLeft') { scrollByCards(-1); startTimer(); }
                });

                // autoplay
                var intervalMs = 4000;
                var autoplay = true;
                var timer = null;
                function startTimer(){ if (!autoplay) return; stopTimer(); timer = setInterval(nextPage, intervalMs); }
                function stopTimer(){ if (timer) { clearInterval(timer); timer = null; } }

                // pause on hover/focus
                container.addEventListener('mouseenter', stopTimer);
                container.addEventListener('mouseleave', startTimer);

                // Recompute pages/dots after images have loaded to avoid sizing mismatch
                function afterImagesLoaded(cb) {
                    var imgs = Array.prototype.slice.call(container.querySelectorAll('img'));
                    if (!imgs.length) return cb();
                    var remaining = imgs.length;
                    var done = function(){ remaining--; if (remaining <= 0) cb(); };
                    imgs.forEach(function(img){
                        if (img.complete) return done();
                        img.addEventListener('load', done);
                        img.addEventListener('error', done);
                    });
                    // safety timeout
                    setTimeout(cb, 1200);
                }

                // resize handling to keep page alignment
                var resizeTimeout = null;
                window.addEventListener('resize', function(){
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(function(){
                        // recompute pages and rebuild dots if breakpoint changed
                        var oldVisible = visibleSlots;
                        visibleSlots = computeVisibleSlots();
                        pages = Math.max(1, Math.ceil(itemCount / visibleSlots));
                        if (oldVisible !== visibleSlots) {
                            // clamp currentPage
                            currentPage = Math.max(0, Math.min(currentPage, pages - 1));
                        }
                        goToPage(currentPage);
                    }, 150);
                });

                // initial setup after images loaded (so card sizes are stable)
                afterImagesLoaded(function(){
                    // refresh list of cards and counts (in case DOM changed while loading)
                    cards = Array.prototype.slice.call(container.querySelectorAll('article'));
                    itemCount = cards.length;
                    visibleSlots = computeVisibleSlots();
                    pages = Math.max(1, Math.ceil(itemCount / visibleSlots));

                    if (maybeHideControls()) return;
                    // ensure container is focusable for keyboard control
                    if (!container.getAttribute('tabindex')) container.setAttribute('tabindex','0');

                    // align to current page (0) and start autoplay
                    goToPage(currentPage);
                    startTimer();
                });
            })();
        </script>
    </div>
</section>
