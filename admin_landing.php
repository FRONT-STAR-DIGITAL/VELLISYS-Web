<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    folio_cache_bust();
    $form = post('form');

    if ($form === 'ticker') {
        $action = post('action');
        if ($action === 'add') {
            $body = mb_substr(trim(post('body')), 0, 220);
            $sort = (int) post('sort');
            if ($body === '') {
                $error = 'Write a line for the top bar.';
            } else {
                if ($sort <= 0) {
                    $max = db_one('SELECT MAX(sort) AS s FROM landing_ticker');
                    $sort = (int) ($max['s'] ?? 0) + 10;
                }
                db_exec('INSERT INTO landing_ticker (body, sort) VALUES (?,?)', 'si', [$body, $sort]);
                flash('Added a top-bar line.');
                redirect('admin_landing.php#top-bar');
            }
        } elseif ($action === 'save') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_ticker WHERE id = ?', 'i', [$id]) : null;
            if (!$row) {
                flash('That top-bar line was not found.', 'err');
                redirect('admin_landing.php#top-bar');
            }
            $body = mb_substr(trim(post('body')), 0, 220);
            $sort = (int) post('sort');
            if ($body === '') {
                $error = 'Write a line for the top bar.';
            } else {
                db_exec('UPDATE landing_ticker SET body=?, sort=? WHERE id=?', 'sii', [$body, $sort, $id]);
                flash('Saved the top-bar line.');
                redirect('admin_landing.php#top-bar');
            }
        } elseif ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_ticker WHERE id = ?', 'i', [$id]) : null;
            if ($row) {
                db_exec('DELETE FROM landing_ticker WHERE id = ?', 'i', [$id]);
                flash('Removed that top-bar line.');
            }
            redirect('admin_landing.php#top-bar');
        }
    } elseif ($form === 'pricing_section') {
        $base = pricing_section_defaults();
        $rates = [];
        foreach (pricing_ugx_rate_defaults() as $code => $fallback) {
            if ($code === 'UGX') {
                continue;
            }
            $val = (float) str_replace(',', '', (string) ($_POST['rate_' . $code] ?? $fallback));
            $rates[$code] = $val > 0 ? $val : $fallback;
        }
        db_exec(
            'REPLACE INTO landing_pricing (id, kicker, heading, lead, clock_label, term_label, register_copy, register_label, countdown_days, countdown_hours, rates_json)
             VALUES (1,?,?,?,?,?,?,?,?,?,?)',
            'sssssssiis',
            [
                mb_substr(post('kicker'), 0, 80),
                mb_substr(post('heading'), 0, 180),
                post('lead'),
                mb_substr(post('clock_label'), 0, 80),
                mb_substr(post('term_label'), 0, 40) ?: 'per year',
                post('register_copy'),
                mb_substr(post('register_label'), 0, 80),
                max(0, min(30, (int) post('countdown_days'))),
                max(0, min(23, (int) post('countdown_hours'))),
                json_encode($rates),
            ]
        );
        flash('Saved package section copy, countdown and rates.');
        redirect('admin_landing.php#packages');
    } elseif ($form === 'package') {
        $action = post('action');
        if ($action === 'add') {
            $name = mb_substr(post('name'), 0, 80);
            if ($name === '') {
                $error = 'Give the package a name.';
            } else {
                $key = pricing_next_key($name);
                $max = db_one('SELECT MAX(sort) AS s FROM landing_packages');
                $sort = (int) post('sort');
                if ($sort <= 0) {
                    $sort = (int) ($max['s'] ?? 0) + 10;
                }
                db_exec(
                    'INSERT INTO landing_packages (pkg_key, name, kicker, ribbon, seats, price_ugx, was_ugx, cta, lead, points, popular, sort)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    'ssssiddsssii',
                    [
                        $key,
                        $name,
                        mb_substr(post('kicker'), 0, 80),
                        mb_substr(post('ribbon'), 0, 80),
                        max(1, min(3, (int) post('seats') ?: 1)),
                        (float) str_replace(',', '', post('price_ugx')),
                        (float) str_replace(',', '', post('was_ugx')),
                        mb_substr(post('cta') ?: ('Select ' . $name), 0, 80),
                        post('lead'),
                        post('points'),
                        post('popular') === '1' ? 1 : 0,
                        $sort,
                    ]
                );
                flash('Added the ' . $name . ' package.');
                redirect('admin_landing.php#packages');
            }
        } elseif ($action === 'save') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_packages WHERE id = ?', 'i', [$id]) : null;
            if (!$row) {
                flash('That package was not found.', 'err');
                redirect('admin_landing.php#packages');
            }
            $name = mb_substr(post('name'), 0, 80);
            if ($name === '') {
                $error = 'Give the package a name.';
            } else {
                db_exec(
                    'UPDATE landing_packages SET name=?, kicker=?, ribbon=?, seats=?, price_ugx=?, was_ugx=?, cta=?, lead=?, points=?, popular=?, sort=? WHERE id=?',
                    'sssiddsssiii',
                    [
                        $name,
                        mb_substr(post('kicker'), 0, 80),
                        mb_substr(post('ribbon'), 0, 80),
                        max(1, min(3, (int) post('seats') ?: 1)),
                        (float) str_replace(',', '', post('price_ugx')),
                        (float) str_replace(',', '', post('was_ugx')),
                        mb_substr(post('cta') ?: ('Select ' . $name), 0, 80),
                        post('lead'),
                        post('points'),
                        post('popular') === '1' ? 1 : 0,
                        (int) post('sort'),
                        $id,
                    ]
                );
                flash('Saved ' . $name . '.');
                redirect('admin_landing.php#packages');
            }
        } elseif ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_packages WHERE id = ?', 'i', [$id]) : null;
            $count = db_one('SELECT COUNT(*) AS c FROM landing_packages');
            if ((int) ($count['c'] ?? 0) <= 1) {
                flash('Keep at least one package on the site.', 'err');
            } elseif ($row) {
                db_exec('DELETE FROM landing_packages WHERE id = ?', 'i', [$id]);
                flash('Removed ' . $row['name'] . '.');
            }
            redirect('admin_landing.php#packages');
        }
    } elseif ($form === 'reviews_section') {
        $defaults = landing_review_section_defaults();
        $kicker = mb_substr(trim(post('kicker')), 0, 80) ?: $defaults['kicker'];
        $heading = mb_substr(trim(post('heading')), 0, 180) ?: $defaults['heading'];
        db_exec(
            'REPLACE INTO landing_review_section (id, kicker, heading) VALUES (1,?,?)',
            'ss',
            [$kicker, $heading]
        );
        flash('Saved testimonial section copy.');
        redirect('admin_landing.php#testimonials');
    } elseif ($form === 'reviews') {
        $action = post('action');
        if ($action === 'add') {
            $name = mb_substr(post('name'), 0, 120);
            $role = mb_substr(post('role'), 0, 160);
            $quote = trim(post('quote'));
            $sort = (int) post('sort');
            if ($name === '' || $quote === '') {
                $error = 'Give the testimonial a name and a quote.';
            } else {
                if ($sort <= 0) {
                    $max = db_one('SELECT MAX(sort) AS s FROM landing_reviews');
                    $sort = (int) ($max['s'] ?? 0) + 10;
                }
                db_exec('INSERT INTO landing_reviews (name, role, quote, sort) VALUES (?,?,?,?)', 'sssi', [$name, $role, $quote, $sort]);
                flash('Added a testimonial from ' . $name . '.');
                redirect('admin_landing.php#testimonials');
            }
        } elseif ($action === 'save') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_reviews WHERE id = ?', 'i', [$id]) : null;
            if (!$row) {
                flash('That testimonial was not found.', 'err');
                redirect('admin_landing.php#testimonials');
            }
            $name = mb_substr(post('name'), 0, 120);
            $role = mb_substr(post('role'), 0, 160);
            $quote = trim(post('quote'));
            $sort = (int) post('sort');
            if ($name === '' || $quote === '') {
                $error = 'Give the testimonial a name and a quote.';
            } else {
                db_exec('UPDATE landing_reviews SET name=?, role=?, quote=?, sort=? WHERE id=?', 'sssii', [$name, $role, $quote, $sort, $id]);
                flash('Saved the testimonial from ' . $name . '.');
                redirect('admin_landing.php#testimonials');
            }
        } elseif ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM landing_reviews WHERE id = ?', 'i', [$id]) : null;
            if ($row) {
                db_exec('DELETE FROM landing_reviews WHERE id = ?', 'i', [$id]);
                flash('Removed the testimonial from ' . $row['name'] . '.');
            }
            redirect('admin_landing.php#testimonials');
        }
    } elseif ($form === 'trust') {
        $action = post('action');
        if ($action === 'add') {
            $name = mb_substr(post('name'), 0, 160);
            $sort = (int) post('sort');
            if ($name === '') {
                $error = 'Give the client a name.';
            } else {
                $up = save_uploaded_image('logo', 'uploads/trust', 'client');
                if (!$up['ok']) {
                    $error = $up['error'] ?? 'Could not save that logo.';
                } elseif ($up['path'] === null) {
                    $error = 'Upload a logo for this client.';
                } else {
                    if ($sort <= 0) {
                        $max = db_one('SELECT MAX(sort) AS s FROM trust_clients');
                        $sort = (int) ($max['s'] ?? 0) + 10;
                    }
                    db_exec('INSERT INTO trust_clients (name, logo_path, sort) VALUES (?,?,?)', 'ssi', [$name, $up['path'], $sort]);
                    flash('Added ' . $name . ' to Clients who trust us.');
                    redirect('admin_landing.php#trust-clients');
                }
            }
        } elseif ($action === 'save') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM trust_clients WHERE id = ?', 'i', [$id]) : null;
            if (!$row) {
                flash('That client was not found.', 'err');
                redirect('admin_landing.php#trust-clients');
            }
            $name = mb_substr(post('name'), 0, 160);
            $sort = (int) post('sort');
            if ($name === '') {
                $error = 'Give the client a name.';
            } else {
                $logoPath = (string) $row['logo_path'];
                $up = save_uploaded_image('logo', 'uploads/trust', 'client');
                if (!$up['ok']) {
                    $error = $up['error'] ?? 'Could not save that logo.';
                } else {
                    if ($up['path'] !== null) {
                        maybe_unlink_upload($logoPath);
                        $logoPath = $up['path'];
                    }
                    db_exec('UPDATE trust_clients SET name=?, logo_path=?, sort=? WHERE id=?', 'ssii', [$name, $logoPath, $sort, $id]);
                    flash('Saved ' . $name . '.');
                    redirect('admin_landing.php#trust-clients');
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM trust_clients WHERE id = ?', 'i', [$id]) : null;
            if ($row) {
                maybe_unlink_upload((string) $row['logo_path']);
                db_exec('DELETE FROM trust_clients WHERE id = ?', 'i', [$id]);
                flash('Removed ' . $row['name'] . ' from the marquee.');
            }
            redirect('admin_landing.php#trust-clients');
        }
    } else {
        $id = (int) post('id');
        $card = $id ? db_one('SELECT * FROM landing_cards WHERE id = ?', 'i', [$id]) : null;
        if (!$card) {
            flash('That card was not found.', 'err');
            redirect('admin_landing.php');
        }
        $title = post('title');
        $body = post('body');
        if ($title === '' || $body === '') {
            $error = 'Title and copy are required.';
        } else {
            $imagePath = (string) $card['image_path'];
            $up = save_uploaded_image('image', 'uploads/landing', (string) $card['slot'], 3_000_000);
            if (!$up['ok']) {
                $error = $up['error'] ?? 'Could not save that picture.';
            } else {
                if ($up['path'] !== null) {
                    maybe_unlink_upload($imagePath);
                    $imagePath = $up['path'];
                }
                db_exec('UPDATE landing_cards SET title=?, body=?, image_path=? WHERE id=?', 'sssi', [$title, $body, $imagePath, $id]);
                flash('Landing card saved.');
                redirect('admin_landing.php');
            }
        }
    }
}

$cards = landing_cards();
$trust = [];
$reviews = [];
$ticker = [];
try {
    $trust = db_all('SELECT * FROM trust_clients ORDER BY sort, id');
} catch (Throwable $e) {
    $trust = [];
}
try {
    $reviews = db_all('SELECT * FROM landing_reviews ORDER BY sort, id');
} catch (Throwable $e) {
    $reviews = [];
}
try {
    $ticker = db_all('SELECT * FROM landing_ticker ORDER BY sort, id');
} catch (Throwable $e) {
    $ticker = [];
}
$pricingSection = pricing_section();
$reviewSection = landing_review_section();
$adminPackages = [];
try {
    $adminPackages = db_all('SELECT * FROM landing_packages ORDER BY sort, id');
} catch (Throwable $e) {
    $adminPackages = [];
}

layout_admin_start('Landing', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('image') ?>Landing page</h1>
    <p class="lede">Change the top-bar lines, the packages, the pictures, the words, the <strong>Clients who trust us</strong> logos, and the scrolling <strong>testimonials</strong> on the public site. The favicon stays the V mark. The header uses the Vellisys logo on its own.</p>
  </div>
  <a class="btn ghost" href="<?= h(url()) ?>" target="_blank" rel="noopener">View site</a>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<h2 class="landing-admin-h" id="top-bar"><?= icon('globe', 20) ?>Top bar</h2>
<p class="lede" style="margin-top:-8px">These lines scroll above the header, separated by a blue |. White and pale blue alternate so the two statements stay distinct. Lower order numbers come first.</p>

<form class="card trust-admin-add" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="ticker">
  <input type="hidden" name="action" value="add">
  <h3 style="margin:0 0 4px">Add a line</h3>
  <label for="ticker-new-body">Statement</label>
  <input id="ticker-new-body" name="body" required maxlength="220" placeholder="Join 100+ businesses and corporate companies using Vellisys">
  <label for="ticker-new-sort">Order <span class="hint">(optional)</span></label>
  <input id="ticker-new-sort" name="sort" type="number" min="0" step="1" placeholder="Auto">
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('plus') ?>Add line</button>
  </div>
</form>

<?php if ($ticker): ?>
  <div class="trust-admin">
    <?php foreach ($ticker as $t): ?>
      <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="ticker">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <label for="ticker-body-<?= (int) $t['id'] ?>">Statement</label>
        <input id="ticker-body-<?= (int) $t['id'] ?>" name="body" required maxlength="220" value="<?= h($t['body']) ?>">
        <label for="ticker-sort-<?= (int) $t['id'] ?>">Order</label>
        <input id="ticker-sort-<?= (int) $t['id'] ?>" name="sort" type="number" required min="0" step="1" value="<?= (int) $t['sort'] ?>">
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit" name="action" value="save"><?= icon('check') ?>Save</button>
          <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this line from the top bar?');"><?= icon('trash') ?>Remove</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="landing-admin-h" id="packages"><?= icon('bank', 20) ?>Packages</h2>
<p class="lede" style="margin-top:-8px">Everything on the public packages block - heading, countdown, currency rates, names, prices, inclusions. Prices are stored in UGX. Header currencies convert from the rates below. Put <code>{currency}</code> in the intro where the live code should appear, and <code>{register}</code> where the register link should go.</p>

<form class="card pricing-admin-section" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="pricing_section">
  <h3 style="margin:0 0 8px">Section copy</h3>
  <div class="pricing-admin-grid">
    <div>
      <label for="pricing-kicker">Kicker</label>
      <input id="pricing-kicker" name="kicker" maxlength="80" value="<?= h($pricingSection['kicker']) ?>">
    </div>
    <div>
      <label for="pricing-heading">Heading</label>
      <input id="pricing-heading" name="heading" maxlength="180" value="<?= h($pricingSection['heading']) ?>">
    </div>
  </div>
  <label for="pricing-lead">Intro</label>
  <textarea id="pricing-lead" name="lead" rows="3"><?= h($pricingSection['lead']) ?></textarea>
  <div class="pricing-admin-grid">
    <div>
      <label for="pricing-clock">Countdown label</label>
      <input id="pricing-clock" name="clock_label" maxlength="80" value="<?= h($pricingSection['clock_label']) ?>">
    </div>
    <div>
      <label for="pricing-term">Price period</label>
      <input id="pricing-term" name="term_label" maxlength="40" value="<?= h($pricingSection['term_label']) ?>">
    </div>
    <div>
      <label for="pricing-days">Countdown days</label>
      <input id="pricing-days" name="countdown_days" type="number" min="0" max="30" step="1" value="<?= (int) $pricingSection['countdown_days'] ?>">
    </div>
    <div>
      <label for="pricing-hours">Countdown extra hours</label>
      <input id="pricing-hours" name="countdown_hours" type="number" min="0" max="23" step="1" value="<?= (int) $pricingSection['countdown_hours'] ?>">
    </div>
  </div>
  <label for="pricing-register-copy">Register line</label>
  <textarea id="pricing-register-copy" name="register_copy" rows="2"><?= h($pricingSection['register_copy']) ?></textarea>
  <label for="pricing-register-label">Register link text</label>
  <input id="pricing-register-label" name="register_label" maxlength="80" value="<?= h($pricingSection['register_label']) ?>">
  <h3 style="margin:18px 0 8px">UGX per 1 unit</h3>
  <p class="hint" style="margin-top:0">UGX stays 1. These averages convert the package prices in the header chooser.</p>
  <div class="pricing-admin-grid">
    <?php foreach ($pricingSection['rates'] as $code => $rate): ?>
      <?php if ($code === 'UGX') continue; ?>
      <div>
        <label for="rate-<?= h($code) ?>"><?= h($code) ?></label>
        <input id="rate-<?= h($code) ?>" name="rate_<?= h($code) ?>" type="number" min="0.01" step="0.01" value="<?= h((string) $rate) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <div class="actions" style="margin-top:14px">
    <button class="btn" type="submit"><?= icon('check') ?>Save section</button>
  </div>
</form>

<form class="card trust-admin-add" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="package">
  <input type="hidden" name="action" value="add">
  <h3 style="margin:0 0 4px">Add a package</h3>
  <label for="pkg-new-name">Name</label>
  <input id="pkg-new-name" name="name" required maxlength="80" placeholder="Quill">
  <label for="pkg-new-kicker">Kicker</label>
  <input id="pkg-new-kicker" name="kicker" maxlength="80" placeholder="Starting package">
  <label for="pkg-new-ribbon">Ribbon <span class="hint">(with Featured)</span></label>
  <input id="pkg-new-ribbon" name="ribbon" maxlength="80" placeholder="Most companies">
  <label for="pkg-new-seats">Logins (1-3)</label>
  <input id="pkg-new-seats" name="seats" type="number" min="1" max="3" step="1" value="1">
  <label for="pkg-new-price">Price UGX</label>
  <input id="pkg-new-price" name="price_ugx" type="number" min="0" step="1" required placeholder="150000">
  <label for="pkg-new-was">Was UGX</label>
  <input id="pkg-new-was" name="was_ugx" type="number" min="0" step="1" placeholder="200000">
  <label for="pkg-new-cta">Button</label>
  <input id="pkg-new-cta" name="cta" maxlength="80" placeholder="Select Quill">
  <label for="pkg-new-lead">Lead</label>
  <textarea id="pkg-new-lead" name="lead" rows="2"></textarea>
  <label for="pkg-new-points">Included <span class="hint">(one line each)</span></label>
  <textarea id="pkg-new-points" name="points" rows="5"></textarea>
  <label class="check"><input type="checkbox" name="popular" value="1"> Featured card</label>
  <label for="pkg-new-sort">Order <span class="hint">(optional)</span></label>
  <input id="pkg-new-sort" name="sort" type="number" min="0" step="1" placeholder="Auto">
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('plus') ?>Add package</button>
  </div>
</form>

<?php if (!$adminPackages): ?>
  <p class="empty">No packages yet. Add one above.</p>
<?php else: ?>
  <div class="landing-admin">
    <?php foreach ($adminPackages as $p): ?>
      <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="package">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <p class="hint" style="margin:0 0 8px">Key <code><?= h((string) $p['pkg_key']) ?></code> - used on checkout and Pesapal.</p>
        <label for="pkg-name-<?= (int) $p['id'] ?>">Name</label>
        <input id="pkg-name-<?= (int) $p['id'] ?>" name="name" required maxlength="80" value="<?= h((string) $p['name']) ?>">
        <label for="pkg-kicker-<?= (int) $p['id'] ?>">Kicker</label>
        <input id="pkg-kicker-<?= (int) $p['id'] ?>" name="kicker" maxlength="80" value="<?= h((string) $p['kicker']) ?>">
        <label for="pkg-ribbon-<?= (int) $p['id'] ?>">Ribbon</label>
        <input id="pkg-ribbon-<?= (int) $p['id'] ?>" name="ribbon" maxlength="80" value="<?= h((string) $p['ribbon']) ?>">
        <label for="pkg-seats-<?= (int) $p['id'] ?>">Logins (1-3)</label>
        <input id="pkg-seats-<?= (int) $p['id'] ?>" name="seats" type="number" min="1" max="3" step="1" required value="<?= (int) $p['seats'] ?>">
        <label for="pkg-price-<?= (int) $p['id'] ?>">Price UGX</label>
        <input id="pkg-price-<?= (int) $p['id'] ?>" name="price_ugx" type="number" min="0" step="1" required value="<?= h((string) (int) $p['price_ugx']) ?>">
        <label for="pkg-was-<?= (int) $p['id'] ?>">Was UGX</label>
        <input id="pkg-was-<?= (int) $p['id'] ?>" name="was_ugx" type="number" min="0" step="1" value="<?= h((string) (int) $p['was_ugx']) ?>">
        <label for="pkg-cta-<?= (int) $p['id'] ?>">Button</label>
        <input id="pkg-cta-<?= (int) $p['id'] ?>" name="cta" maxlength="80" value="<?= h((string) $p['cta']) ?>">
        <label for="pkg-lead-<?= (int) $p['id'] ?>">Lead</label>
        <textarea id="pkg-lead-<?= (int) $p['id'] ?>" name="lead" rows="3"><?= h((string) $p['lead']) ?></textarea>
        <label for="pkg-points-<?= (int) $p['id'] ?>">Included <span class="hint">(one line each)</span></label>
        <textarea id="pkg-points-<?= (int) $p['id'] ?>" name="points" rows="6"><?= h((string) $p['points']) ?></textarea>
        <label class="check"><input type="checkbox" name="popular" value="1"<?= (int) $p['popular'] === 1 ? ' checked' : '' ?>> Featured card</label>
        <label for="pkg-sort-<?= (int) $p['id'] ?>">Order</label>
        <input id="pkg-sort-<?= (int) $p['id'] ?>" name="sort" type="number" min="0" step="1" required value="<?= (int) $p['sort'] ?>">
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit" name="action" value="save"><?= icon('check') ?>Save</button>
          <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this package from the public site?');"><?= icon('trash') ?>Remove</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="landing-admin-h">Page cards</h2>
<div class="landing-admin">
  <?php foreach ($cards as $c): ?>
    <form class="card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="card">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <img class="landing-admin-pic" src="<?= h(landing_card_image_url($c)) ?>" alt="">
      <p class="hint" style="margin:8px 0 0"><?= h($c['section']) ?> · <?= h($c['slot']) ?></p>
      <label for="title-<?= (int) $c['id'] ?>">Title</label>
      <input id="title-<?= (int) $c['id'] ?>" name="title" required value="<?= h($c['title']) ?>">
      <label for="body-<?= (int) $c['id'] ?>">Copy</label>
      <textarea id="body-<?= (int) $c['id'] ?>" name="body" rows="4" required><?= h($c['body']) ?></textarea>
      <label for="image-<?= (int) $c['id'] ?>">Replace picture</label>
      <input id="image-<?= (int) $c['id'] ?>" name="image" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save card</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>

<h2 class="landing-admin-h" id="trust-clients"><?= icon('clients', 20) ?>Clients who trust us</h2>
<p class="lede" style="margin-top:-8px">These names and logos scroll on the public home page. Add a company, upload its mark, and set the order (lower numbers come first).</p>

<form class="card trust-admin-add" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="trust">
  <input type="hidden" name="action" value="add">
  <h3 style="margin:0 0 4px">Add a client</h3>
  <label for="trust-new-name">Name</label>
  <input id="trust-new-name" name="name" required maxlength="160" placeholder="Company name">
  <label for="trust-new-logo">Logo</label>
  <input id="trust-new-logo" name="logo" type="file" required accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
  <label for="trust-new-sort">Order <span class="hint">(optional)</span></label>
  <input id="trust-new-sort" name="sort" type="number" min="0" step="1" placeholder="Auto">
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('plus') ?>Add to marquee</button>
  </div>
</form>

<?php if (!$trust): ?>
  <p class="empty">No clients on the marquee yet. Add one above.</p>
<?php else: ?>
  <div class="trust-admin">
    <?php foreach ($trust as $t): ?>
      <form class="card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="trust">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <div class="trust-admin-preview">
          <img src="<?= h(trust_client_logo_url($t)) ?>" alt="">
        </div>
        <label for="trust-name-<?= (int) $t['id'] ?>">Name</label>
        <input id="trust-name-<?= (int) $t['id'] ?>" name="name" required maxlength="160" value="<?= h($t['name']) ?>">
        <label for="trust-sort-<?= (int) $t['id'] ?>">Order</label>
        <input id="trust-sort-<?= (int) $t['id'] ?>" name="sort" type="number" required min="0" step="1" value="<?= (int) $t['sort'] ?>">
        <label for="trust-logo-<?= (int) $t['id'] ?>">Replace logo</label>
        <input id="trust-logo-<?= (int) $t['id'] ?>" name="logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit" name="action" value="save"><?= icon('check') ?>Save</button>
          <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this client from the marquee?');"><?= icon('trash') ?>Remove</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="landing-admin-h" id="testimonials"><?= icon('letter', 20) ?>Testimonials</h2>
<p class="lede" style="margin-top:-8px">These quotes scroll on the public home page. Edit the heading, then add or remove people. An empty list hides the block.</p>

<form class="card pricing-admin-section" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="reviews_section">
  <h3 style="margin:0 0 8px">Section copy</h3>
  <div class="pricing-admin-grid">
    <div>
      <label for="review-kicker">Kicker</label>
      <input id="review-kicker" name="kicker" maxlength="80" value="<?= h($reviewSection['kicker']) ?>">
    </div>
    <div>
      <label for="review-heading">Heading</label>
      <input id="review-heading" name="heading" maxlength="180" value="<?= h($reviewSection['heading']) ?>">
    </div>
  </div>
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('check') ?>Save section</button>
  </div>
</form>

<form class="card trust-admin-add" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="reviews">
  <input type="hidden" name="action" value="add">
  <h3 style="margin:0 0 4px">Add a testimonial</h3>
  <label for="review-new-name">Name</label>
  <input id="review-new-name" name="name" required maxlength="120" placeholder="Priya Menon">
  <label for="review-new-role">Role / company</label>
  <input id="review-new-role" name="role" maxlength="160" placeholder="Accounts, Harbour &amp; Co.">
  <label for="review-new-quote">Quote</label>
  <textarea id="review-new-quote" name="quote" rows="3" required maxlength="800" placeholder="What they said"></textarea>
  <label for="review-new-sort">Order <span class="hint">(optional)</span></label>
  <input id="review-new-sort" name="sort" type="number" min="0" step="1" placeholder="Auto">
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('plus') ?>Add testimonial</button>
  </div>
</form>

<?php if (!$reviews): ?>
  <p class="empty">No testimonials on the public page. Add one above.</p>
<?php else: ?>
  <div class="trust-admin">
    <?php foreach ($reviews as $r): ?>
      <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="reviews">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <label for="review-name-<?= (int) $r['id'] ?>">Name</label>
        <input id="review-name-<?= (int) $r['id'] ?>" name="name" required maxlength="120" value="<?= h($r['name']) ?>">
        <label for="review-role-<?= (int) $r['id'] ?>">Role / company</label>
        <input id="review-role-<?= (int) $r['id'] ?>" name="role" maxlength="160" value="<?= h($r['role']) ?>">
        <label for="review-quote-<?= (int) $r['id'] ?>">Quote</label>
        <textarea id="review-quote-<?= (int) $r['id'] ?>" name="quote" rows="4" required maxlength="800"><?= h($r['quote']) ?></textarea>
        <label for="review-sort-<?= (int) $r['id'] ?>">Order</label>
        <input id="review-sort-<?= (int) $r['id'] ?>" name="sort" type="number" required min="0" step="1" value="<?= (int) $r['sort'] ?>">
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit" name="action" value="save"><?= icon('check') ?>Save</button>
          <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this testimonial from the public page?');"><?= icon('trash') ?>Remove</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
