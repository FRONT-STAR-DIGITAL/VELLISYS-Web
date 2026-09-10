<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$brand = branding();

$shot = static function (string $file, string $alt): string {
    $rel = 'assets/img/tutorials/' . $file;
    if (!is_file(ROOT_PATH . '/' . $rel)) {
        return '';
    }
    return '<figure class="tut-shot"><img src="' . h(url($rel)) . '" alt="' . h($alt) . '"></figure>';
};

$lessons = [
    [
        'id' => 'desk',
        'icon' => 'desk',
        'title' => 'The desk',
        'file' => 'desk.png',
        'alt' => 'Vellisys desk home with open invoices and this month’s totals',
        'lead' => 'Sign in and you land on Desk. That is the morning list: who still owes you, what you invoiced this month, and a short trail of recent documents.',
        'points' => [
            'Start here every day. Overdue invoices sit at the top so you chase them first.',
            'Quick add (the plus on the top bar) opens a quotation, invoice, receipt, expense, letter or client without hunting the menu.',
            'The coloured rail on the left is your company. Hover it on a computer, or use the menu button on a phone.',
        ],
    ],
    [
        'id' => 'clients',
        'icon' => 'clients',
        'title' => 'Clients',
        'file' => 'clients.png',
        'alt' => 'Clients list with names, emails and document counts',
        'lead' => 'Every quotation and invoice hangs off a client. Add the people you bill before you write the first sheet.',
        'points' => [
            'Open Clients, then New client. Name, email and phone are enough. TIN and address print on the sheet when you have them.',
            'Open a name to see their quotations, invoices, receipts and letters in one place.',
            'Keep one record per customer. Duplicate names split their history and confuse Debtors.',
        ],
    ],
    [
        'id' => 'quotations',
        'icon' => 'quotation',
        'title' => 'Quotations',
        'file' => 'quotation.png',
        'alt' => 'A branded quotation ready to share',
        'lead' => 'A quotation is the offer. Write it in your logo and colours, send it, then convert it when they say yes.',
        'points' => [
            'New quotation, pick the client, add lines: item, description, qty, unit price, VAT Y or N.',
            'Share the branded sheet, or Email it in one click. Mail leaves from the company mailbox Vellisys assigned, not your personal inbox.',
            'When they accept, convert the quotation to an invoice. The lines copy across so you do not retype them.',
        ],
    ],
    [
        'id' => 'invoices',
        'icon' => 'invoice',
        'title' => 'Invoices',
        'file' => 'invoice.png',
        'alt' => 'A branded invoice with totals and payment details',
        'lead' => 'Invoices are what you are owed. Due dates feed Debtors. Part payments stay honest.',
        'points' => [
            'Issue in UGX or USD. The rate lives in Settings (1 USD = n UGX) so reports can add them up.',
            'Print, share a link, or email the sheet. The letterhead is yours: logo, three colours, bank details, TIN.',
            'Record a receipt against the invoice when money lands. The balance drops. Full or part - both work.',
        ],
    ],
    [
        'id' => 'receipts',
        'icon' => 'receipt',
        'title' => 'Receipts and debtors',
        'file' => 'receipt.png',
        'alt' => 'Receipt showing amount received and amount still due',
        'lead' => 'A receipt is proof you were paid. Debtors is the list of who has not finished paying.',
        'points' => [
            'Open the invoice and record a receipt, or start from Receipts. The sheet shows RECEIVED and DUE.',
            'Debtors ages the unpaid balances: current, 1-30, 31-60, and so on. Use it on Friday to chase.',
            'Never delete a paid invoice to “clean up”. The receipt is the history your auditor wants.',
        ],
    ],
    [
        'id' => 'expenses',
        'icon' => 'expense',
        'title' => 'Expenses and creditors',
        'file' => 'expenses.png',
        'alt' => 'Expense cards and creditors awaiting payment',
        'lead' => 'Money out is an expense. Unpaid supplier bills sit on Creditors until you mark them paid.',
        'points' => [
            'Log the supplier, the category, the VAT, the date. That is what Reports uses for the pie chart.',
            'Pay from the expense or from Creditors. The same branded sheet can go back to the supplier.',
            'Keep personal spend off this desk. Vellisys is the company books.',
        ],
    ],
    [
        'id' => 'letters',
        'icon' => 'letter',
        'title' => 'Correspondence',
        'file' => 'letter.png',
        'alt' => 'Headed correspondence on company stationery',
        'lead' => 'Headed notes - demands, cover letters, introductions - use the same logo as the invoices.',
        'points' => [
            'Write the body on Correspondence. It stays editable. Pick a letter layout in Settings → Templates.',
            'Email or print. Clients see you, not a generic PDF from an accounting package.',
        ],
    ],
    [
        'id' => 'reports',
        'icon' => 'reports',
        'title' => 'Reports',
        'file' => 'reports.png',
        'alt' => 'Reports with income, expenses, a time series and debtors aging',
        'lead' => 'Reports tot up the period you pick: today, this month, last month, or a from/to range.',
        'points' => [
            'Income is invoiced net. Expenses are spent net. VAT due is output minus input.',
            'Export CSV when you need the numbers in a spreadsheet. The charts are for the meeting; the CSV is for the file.',
            'Filter before you export so you are not sending the whole year by accident.',
        ],
    ],
    [
        'id' => 'settings',
        'icon' => 'settings',
        'title' => 'Settings and brand',
        'file' => 'settings.png',
        'alt' => 'Settings with logo upload and three brand colours',
        'lead' => 'Logo, three colours, TIN, bank, document prefix, and the UGX/USD rate. One design prints on every sheet.',
        'points' => [
            'Primary paints the desk. Accent and deep colour the document designs.',
            'The sending mailbox is assigned by Vellisys. You can see the address. You cannot change the password. That keeps invoices leaving as the company, not as whoever last signed in.',
            'If a colour or logo is wrong, fix it here. Old documents keep the layout you pick now when you reprint.',
        ],
    ],
    [
        'id' => 'email',
        'icon' => 'send',
        'title' => 'Email a sheet',
        'file' => 'email.png',
        'alt' => 'Email form sending an invoice from the company mailbox',
        'lead' => 'One click sends the quotation or invoice to the client, signed as your company, with your logo in the letter.',
        'points' => [
            'Open the document → Email. To, subject and a short note are filled in. Send.',
            'The From address is the Hostinger mailbox Vellisys put on your company. Replies come back there.',
            'If Send says the mailbox is not assigned yet, tell Vellisys. Until then you can still print and share a link.',
        ],
    ],
];

layout_start('Tutorials', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('book') ?>Tutorials</h1>
    <p class="lede">How <?= h($brand['name']) ?> gets the most from Vellisys: branded books, one desk, mail that looks like you.</p>
  </div>
  <a class="btn" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation', 16) ?>New quotation</a>
</div>

<nav class="tut-toc" aria-label="Lessons">
  <?php foreach ($lessons as $lesson): ?>
    <a href="#<?= h($lesson['id']) ?>"><?= icon($lesson['icon'], 16) ?><?= h($lesson['title']) ?></a>
  <?php endforeach; ?>
</nav>

<div class="tut-lead card">
  <p>Vellisys is not a generic ledger. Every sheet leaves in <strong><?= h($brand['name']) ?></strong> colours, and email leaves from the company mailbox Vellisys assigned. Work the loop: quote → invoice → receipt, keep expenses honest, read Reports on Friday. That is how you stop leaking money and look like a firm that has its books.</p>
</div>

<?php foreach ($lessons as $i => $lesson): ?>
  <article class="tut-lesson card" id="<?= h($lesson['id']) ?>">
    <div class="tut-copy">
      <p class="tut-num">Lesson <?= $i + 1 ?></p>
      <h2><?= icon($lesson['icon'], 20) ?><?= h($lesson['title']) ?></h2>
      <p class="lede"><?= h($lesson['lead']) ?></p>
      <ul>
        <?php foreach ($lesson['points'] as $point): ?>
          <li><?= h($point) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?= $shot($lesson['file'], $lesson['alt']) ?>
  </article>
<?php endforeach; ?>

<div class="tut-help card">
  <h2><?= icon('phone', 16) ?>Still stuck?</h2>
  <p class="lede">Call <?= h(implode(' or ', product_phones())) ?>, or write <?= h(product_email()) ?>. The same mailbox that welcomed you will answer.</p>
</div>
<?php layout_end(); ?>
