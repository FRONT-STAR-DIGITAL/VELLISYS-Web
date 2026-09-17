<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$admin = search_is_platform_portal();
if ($admin) {
    require_platform();
} else {
    require_member();
}

$q = search_query();
$data = $q !== '' ? search_run($q, 24) : [
    'documents' => [],
    'clients' => [],
    'products' => [],
    'companies' => [],
    'signups' => [],
    'exact' => null,
];

$groups = $admin
    ? [
        'companies' => ['Companies', 'building'],
        'documents' => ['Documents', 'file'],
        'signups' => ['Sign-ups', 'letter'],
    ]
    : [
        'documents' => ['Documents', 'file'],
        'clients' => ['Clients', 'clients'],
        'products' => ['Products', 'package'],
    ];

$total = 0;
foreach (array_keys($groups) as $k) {
    $total += count($data[$k] ?? []);
}

if ($admin) {
    layout_admin_start('Search', $user);
} else {
    layout_start('Search', $user);
}
?>
<div class="page-head">
  <div>
    <h1><?= icon('search') ?>Search</h1>
    <p class="lede"><?= $admin ? 'Find a company, a document number, or a sign-up across the platform.' : 'Type a document code, a client name, or a product. An exact document number shows whether that sheet is still valid.' ?></p>
  </div>
</div>

<form class="card search-page-form" method="get" action="<?= h(url('search.php')) ?>">
  <label for="search-q">Look up</label>
  <div class="search-page-row">
    <span class="top-search-icon" aria-hidden="true"><?= icon('search', 18) ?></span>
    <input id="search-q" name="q" type="search" value="<?= h($q) ?>" placeholder="<?= h(search_placeholder()) ?>" autofocus>
    <button class="btn" type="submit"><?= icon('search', 16) ?>Search</button>
  </div>
</form>

<?php if ($q === ''): ?>
  <p class="empty">Type a document number such as INV-12, a client, or a product name.</p>
<?php else: ?>
  <?php if (!empty($data['exact'])): ?>
    <?php $ex = $data['exact']; ?>
    <div class="card search-exact<?= $ex['valid'] ? ' is-valid' : ' is-void' ?>">
      <p class="search-exact-kicker"><?= $ex['valid'] ? 'Valid document' : 'Void document' ?></p>
      <a href="<?= h($ex['href']) ?>">
        <strong><?= h($ex['title']) ?></strong>
        <span><?= h($ex['subtitle']) ?></span>
      </a>
    </div>
  <?php endif; ?>
  <?php if ($total === 0): ?>
    <p class="empty">Nothing matched “<?= h($q) ?>”. Check the spelling, or try the document number as printed on the sheet.</p>
  <?php endif; ?>
  <?php foreach ($groups as $key => [$title, $iconName]): ?>
    <?php $rows = $data[$key] ?? []; ?>
    <?php if (!$rows) { continue; } ?>
    <section class="card search-group">
      <h2><?= icon($iconName, 18) ?><?= h($title) ?></h2>
      <ul class="search-hits">
        <?php foreach ($rows as $hit): ?>
          <li>
            <a href="<?= h($hit['href']) ?>">
              <strong><?= h($hit['title']) ?></strong>
              <span><?= h($hit['subtitle']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php layout_end(); ?>
