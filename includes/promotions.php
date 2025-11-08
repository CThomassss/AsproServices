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
                <h2 class="text-3xl font-semibold tracking-tight text-white md:text-4xl">Promotions &amp; offres du moment</h2>
            </div>
        </div>

        <style>
            /* Carousel: show up to 3 items per row on desktop, responsive single item on small screens */
            .promotions-wrap{position:relative;max-width:1300px;margin-left:auto;margin-right:auto}
            #promotions-carousel{display:flex;gap:2rem;overflow-x:auto;scroll-behavior:smooth;padding-bottom:1rem;-webkit-overflow-scrolling:touch}
            /* Calculate 3 items visible accounting for gaps */
            #promotions-carousel article{flex:0 0 calc((100% - 4rem) / 3);min-width:280px}
            #promotions-prev,#promotions-next{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.6);color:#ffd24d;border:none;padding:0.5rem 0.75rem;border-radius:999px;cursor:pointer;z-index:10}
            #promotions-prev{left:-1.5rem}
            #promotions-next{right:-1.5rem}
            /* On narrower screens show one item and hide arrows */
            @media (max-width:900px){#promotions-carousel article{flex:0 0 80%;min-width:260px} #promotions-prev,#promotions-next{display:none}}
        </style>

        <div class="mt-14 promotions-wrap">
            <?php if (empty($promotions)): ?>
                <div class="text-white/70">Aucune promotion disponible pour le moment.</div>
            <?php else: ?>
                <button id="promotions-prev" aria-label="Previous">‹</button>
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
                                <a class="inline-block rounded bg-white/5 px-3 py-2 text-sm font-semibold text-accent hover:bg-white/10" href="<?= htmlspecialchars($pdfUrl) ?>" rel="noopener noreferrer" target="_blank">Télécharge le catalogue</a>
                            </div>
                        <?php endif; ?>
                        <?php if ($validLabel): ?>
                            <p class="mt-auto text-xs font-semibold uppercase tracking-[0.3em] text-accent">Offre valable jusqu'au<br><?= $validLabel ?></p>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
                </div>
                <button id="promotions-next" aria-label="Next">›</button>
            <?php endif; ?>
        </div>

        <script>
            (function(){
                var container = document.getElementById('promotions-carousel');
                var btnPrev = document.getElementById('promotions-prev');
                var btnNext = document.getElementById('promotions-next');
                if (!container) return;

                function scrollByPage(dir){
                    // Prefer scrolling by one card width when possible
                    var card = container.querySelector('article');
                    var gap = parseInt(getComputedStyle(container).gap) || 32;
                    var scrollAmount = container.clientWidth * 0.9; // fallback
                    if (card) {
                        var w = card.getBoundingClientRect().width;
                        scrollAmount = Math.round(w + gap);
                    }
                    container.scrollBy({left: dir * scrollAmount, behavior: 'smooth'});
                }

                var itemCount = container.querySelectorAll('article').length;
                var visibleSlots = 3; // number of cards visible before enabling scroll/autoplay

                if (itemCount <= visibleSlots) {
                    // Not enough items: hide controls and don't autoplay
                    if (btnPrev) btnPrev.style.display = 'none';
                    if (btnNext) btnNext.style.display = 'none';
                } else {
                    if (btnPrev) btnPrev.addEventListener('click', function(e){ e.preventDefault(); scrollByPage(-1); });
                    if (btnNext) btnNext.addEventListener('click', function(e){ e.preventDefault(); scrollByPage(1); });

                    // Autoplay: advance every 4s; pause on hover
                    var autoplay = true;
                    var intervalMs = 4000;
                    var timer = null;
                    function startTimer(){ if (!autoplay) return; timer = setInterval(function(){ scrollByPage(1); }, intervalMs); }
                    function stopTimer(){ if (timer) { clearInterval(timer); timer = null; } }
                    container.addEventListener('mouseenter', stopTimer);
                    container.addEventListener('mouseleave', startTimer);
                    startTimer();
                }
            })();
        </script>
    </div>
</section>
