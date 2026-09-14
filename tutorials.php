<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$brand = branding();
$customDoc = company_custom_doc();
$customTitle = (string) ($customDoc['title'] ?? 'Custom document');

$shot = static function (string $file, string $alt): string {
    $rel = 'assets/img/tutorials/' . $file;
    $full = ROOT_PATH . '/' . $rel;
    if (!is_file($full)) {
        return '';
    }
    return '<figure class="tut-shot"><img src="' . h(url($rel) . '?v=' . filemtime($full)) . '" alt="' . h($alt) . '" loading="lazy" decoding="async"></figure>';
};

$lessons = [
    [
        'id' => 'desk',
        'icon' => 'desk',
        'title' => 'The desk',
        'file' => 'desk.png',
        'alt' => 'Desk home with greeting card, main currency, USD rate, open invoices and this month\'s totals',
        'lead' => 'Sign in and you land on Desk. The greeting card names the company, you, the home currency and the USD rate. Below that is who still owes you, this month\'s totals, and a short trail of recent documents.',
        'points' => [
            'Start here every day. Overdue invoices sit at the top so you chase them first.',
            'Quick add (the plus on the top bar) opens a quotation, invoice, receipt, expense, letter, email or client without hunting the menu.',
            'The bell (when Planner is on) lists deadlines and invoices you can Receive. The rail on the left is your company. Hover it on a computer, or use the menu button on a phone.',
        ],
    ],
    [
        'id' => 'activities',
        'icon' => 'clock',
        'title' => 'Activities',
        'file' => 'activities.png',
        'alt' => 'Company activity log of issued documents, payments and mail',
        'lead' => 'Activities is on every package. It is the desk history: who issued what, who was paid, which mail left, and when branding was saved. It is not a visitor log of every page click.',
        'points' => [
            'Open Activities from the rail, from Desk, or from the Activities button on the public packages.',
            'Filter by documents, payments, email, clients, settings or planner. Search a number or a name.',
            'Older sheets already on the books are copied in the first time the log is created, so you are not looking at an empty page.',
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
            'New quotation, pick the client, add lines: item, description, qty, unit price, tax Y or N.',
            'Share the branded sheet, or Email it in one click. Mail leaves from the company mailbox Vellisys assigned, not your personal inbox.',
            'When they accept, convert the quotation to an invoice. The lines copy across so you do not retype them.',
        ],
    ],
    [
        'id' => 'convert',
        'icon' => 'convert',
        'title' => 'Convert a quotation',
        'file' => 'convert.png',
        'alt' => 'Quotation actions including convert to invoice',
        'lead' => 'Do not retype an accepted quote. Open it and press Invoice. Vellisys copies the client, the lines, the tax and the currency onto a new invoice.',
        'points' => [
            'The quotation stays on file. The new invoice gets its own number so you can still show the original offer.',
            'Edit the invoice if the job changed - extra lines, a due date, or a different currency - then share or email it.',
            'If they never accepted, leave the quotation as it is. Void it only when the offer is dead.',
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
            'Issue in your currency, or in USD. The rate lives in Settings and on the Desk greeting card so reports can add them up.',
            'Print, share a link, or email the sheet. The letterhead is yours: logo, two colours, bank details, TIN. On a phone the page is the same A4 sheet, scaled to fit.',
            'Record a receipt against the invoice when money lands. The balance drops. Full or part - both work. Receive is also on the notifications bell when Planner is on.',
        ],
    ],
    [
        'id' => 'receipts',
        'icon' => 'receipt',
        'title' => 'Receipts',
        'file' => 'receipt.png',
        'alt' => 'Receipt showing amount received and amount still due',
        'lead' => 'A receipt is proof you were paid. It sits against the invoice so Debtors and Reports stay honest.',
        'points' => [
            'Open the invoice and record a receipt, or start from Receipts. The sheet shows RECEIVED and DUE.',
            'Part payments are allowed. Each receipt drops the balance. Never rewrite the invoice to hide a part payment.',
            'The receipt prints in the same logo and colours as the invoice. Email it from Share, or print a copy for the file.',
        ],
    ],
    [
        'id' => 'delivery',
        'icon' => 'truck',
        'title' => 'Delivery notes',
        'file' => 'delivery.png',
        'alt' => 'A branded delivery note with quantities to send',
        'lead' => 'A delivery note is what leaves with the goods. Quantities, not prices. The client signs the sheet, not a quote.',
        'points' => [
            'New delivery note, pick the client, add the items and quantities that are going out.',
            'Print it for the driver, or share the branded sheet. Prices stay on the invoice; this sheet is the packing list.',
            'Vellisys only shows Delivery notes when your desk is set up to use them. Ask Vellisys if the tab is missing.',
        ],
    ],
    [
        'id' => 'expenses',
        'icon' => 'expense',
        'title' => 'Expenses',
        'file' => 'expenses.png',
        'alt' => 'Expense list with suppliers, categories and amounts',
        'lead' => 'Money out is an expense. Log the supplier, the category, the tax and the date so Reports can tot it up.',
        'points' => [
            'Record expense from the menu or Quick add. Pick or add the supplier, then the lines.',
            'Category is what the pie chart uses. Keep personal spend off this desk - Vellisys is the company books.',
            'Unpaid bills stay on Creditors until you mark them paid. Pay from the expense, or from that list.',
        ],
    ],
    [
        'id' => 'letters',
        'icon' => 'letter',
        'title' => 'Letters',
        'file' => 'letter.png',
        'alt' => 'Headed letter on the company document design',
        'lead' => 'Headed letters - demands, cover notes, introductions - use the same logo and document design as the invoices.',
        'points' => [
            'Write the body on Letters. It stays editable. The paper around it is the design you picked in Settings under Templates.',
            'Download Word template when you want the company letterhead in Microsoft Word, then type your own content.',
            'Print, share or email from the same row as any other document.',
        ],
    ],
    [
        'id' => 'custom',
        'icon' => 'file',
        'title' => $customTitle,
        'file' => 'custom.png',
        'alt' => 'A custom branded document with company fields',
        'lead' => 'A custom document is a form Vellisys built for this desk - not a letter, not an invoice. The title on the menu is what you named it.',
        'points' => [
            'Open ' . $customTitle . ' and fill the fields Vellisys set: job number, site, whatever this company prints besides a quote.',
            'A body is optional. Tick that in Settings if you need a paragraph under the fields.',
            'If the tab is missing, this desk was onboarded without custom documents. Ask Vellisys to switch it on.',
        ],
    ],
    [
        'id' => 'email',
        'icon' => 'send',
        'title' => 'Email from the desk',
        'file' => 'email.png',
        'alt' => 'Email form sending from the company mailbox',
        'lead' => 'Sheets and letters leave from the Hostinger mailbox Vellisys assigned to the company, with your logo on white.',
        'points' => [
            'Share, then Email on a quotation, invoice, receipt, expense or headed letter sends that branded sheet.',
            'Email in the menu is for custom letters, Remind on Debtors, and Message on Creditors. Replies come back to the company mailbox.',
            'If Send says the mailbox is not assigned yet, tell Vellisys. Until then you can still print and share a link.',
        ],
    ],
    [
        'id' => 'debtors',
        'icon' => 'clients',
        'title' => 'Debtors',
        'file' => 'debtors.png',
        'alt' => 'Debtors list of clients who still owe money',
        'lead' => 'Debtors is who has not finished paying. Open invoices, aged balances, one place to chase.',
        'points' => [
            'The list groups by client and ages the unpaid balances: current, 1-30, 31-60, and so on.',
            'Open an invoice to take a receipt, or use Remind to email the client from the company mailbox. Receive on the bell does the same job from Desk.',
            'Export CSV when you need the aging in a spreadsheet. Never delete a paid invoice to clean up.',
        ],
    ],
    [
        'id' => 'creditors',
        'icon' => 'bank',
        'title' => 'Creditors',
        'file' => 'creditors.png',
        'alt' => 'Creditors list of unpaid supplier bills',
        'lead' => 'Creditors is what the company still owes suppliers. Pay the bill, or write to them from the same row.',
        'points' => [
            'Each unpaid expense sits here with amount, paid so far, and balance.',
            'Pay from the row, or Message the supplier - that letter leaves from the company mailbox.',
            'Keep supplier names consistent so the same firm does not appear twice on the list.',
        ],
    ],
    [
        'id' => 'planner',
        'icon' => 'calendar',
        'title' => 'Planner',
        'file' => 'planner.png',
        'alt' => 'Planner with this week\'s events, priority notes and budget versus actuals',
        'lead' => 'Planner is notes, tasks to hit, a budget and a calendar for programmes, appointments and deadlines. It is on Business and Pro desks for the company admin.',
        'points' => [
            'Open Planner from the rail. Add a note, a task or goal, a budget line for the month, or an event on the calendar.',
            'Essential and high notes, open tasks that are due, plus invoices that are due, also appear in the bell on the top bar.',
            'If the tab is missing, this desk is on Starter, or Vellisys has not switched Planner on. Ask for Business or Pro.',
        ],
    ],
    [
        'id' => 'branches',
        'icon' => 'pin',
        'title' => 'Branches',
        'file' => '',
        'alt' => '',
        'lead' => 'Vellisys Business allows up to 2 branches and Pro up to 3, including Head office. Several people can share a branch. Vellisys Start is Head office only.',
        'points' => [
            'Open Branches on the rail. Add a shop or city name and its address, up to the branch cap on the package.',
            'Sheets issued from a branch print that address. Head office keeps using the Settings address.',
            'Assign staff so their work is recorded there. Several logins can sit on one branch.',
            'The company admin opens Activities and chips for every branch, Head office, or the whole desk.',
        ],
    ],
    [
        'id' => 'stock',
        'icon' => 'package',
        'title' => 'Stock and Sale',
        'file' => '',
        'alt' => '',
        'lead' => 'When Vellisys switches stock on, the rail gets Stock and Sale. Purchases sit as a tab inside Stock. The Ofagros demo already has this on.',
        'points' => [
            'Open the day: type the cash in the till, then sell. Close with the cash at the end. Today\'s income, spend, net and tax sit on Day.',
            'Add products, or download the Excel, fill it and upload. Low stock shows when quantity hits the reorder level.',
            'Sale: type a product, it fills. Discount and part pay sit under the list. Save prints a receipt. Unpaid sales sit on Debtors.',
            'Purchases become expenses. Unpaid purchases sit on Creditors. Quotes and invoices type from the same products.',
            'Settings has a daily backup you can download or restore.',
        ],
    ],
    [
        'id' => 'notify',
        'icon' => 'bell',
        'title' => 'Notifications',
        'file' => 'notify.png',
        'alt' => 'Notifications window with upcoming invoices and a Receive action',
        'lead' => 'The bell on the top bar is the morning list from Planner: events, essential notes, and invoices that still need a receipt.',
        'points' => [
            'Open the bell. Each row has Dismiss, plus Open, View, Done or Receive.',
            'Receive on an invoice opens the receipt form. The word is white on the brand button so you can read it.',
            'Open Planner at the foot of the list when you want the full week, not only the next few items.',
        ],
    ],
    [
        'id' => 'pnl',
        'icon' => 'reports',
        'title' => 'Profit and loss',
        'file' => 'pnl.png',
        'alt' => 'Profit and loss with income, expenses, net profit and cash movement charts',
        'lead' => 'P&L tot up income, expenses, refunds and returns for the dates you pick, with net profit on one desk. It is on Pro desks for the company admin.',
        'points' => [
            'Open P&L. Filter the period the same way as Reports. Ledger, Refund and Return sit next to New entry.',
            'Refunds and returns are not on the main rail - they live here so the profit figure stays honest.',
            'If the tab is missing, ask Vellisys to put the desk on Pro, or to switch P&L on.',
        ],
    ],
    [
        'id' => 'reports',
        'icon' => 'reports',
        'title' => 'Reports',
        'file' => 'reports.png',
        'alt' => 'Reports with income, expenses, a time series and debtors aging',
        'lead' => 'Reports tot up the period you pick: today, this month, last month, or a from/to range. Only the company admin opens this tab.',
        'points' => [
            'Income is invoiced net. Expenses are spent net.',
            'The tax table lists each taxed item, the sheet it sat on, and the receipt that collected it. Tax payable is output less input.',
            'Export CSV when you need the numbers in a spreadsheet. The charts are for the meeting; the CSV is for the file.',
            'Filter before you export so you are not sending the whole year by accident.',
        ],
    ],
    [
        'id' => 'dates',
        'icon' => 'calendar',
        'title' => 'Date filters',
        'file' => 'dates.png',
        'alt' => 'Document list with this month, last month and custom date chips',
        'lead' => 'Every list - invoices, receipts, expenses, reports - can shrink to a period. Use the chips above the table, or type a from and to date.',
        'points' => [
            'This month is the usual view for a Friday meeting. Last month is for the file you already closed.',
            'Custom from/to is for a job that crossed months, or a tax quarter.',
            'The same chips sit on Reports and P&L, so the charts match the table you just filtered.',
        ],
    ],
    [
        'id' => 'share',
        'icon' => 'share',
        'title' => 'Share, print and WhatsApp',
        'file' => 'share.png',
        'alt' => 'Document actions including print, share, email and WhatsApp',
        'lead' => 'Every sheet can leave the desk without a PDF attachment hunt. Print it, email it, or send the link on WhatsApp.',
        'points' => [
            'Open any quotation, invoice, receipt or letter. Print opens the sheet. Share offers WhatsApp and Email.',
            'The share link is the branded page the client sees. They can print from there. No login required. On a phone the sheet is still the A4 page, scaled to the screen.',
            'WhatsApp opens with the document link already in the message. Email sends the sheet from the company mailbox.',
        ],
    ],
    [
        'id' => 'settings',
        'icon' => 'settings',
        'title' => 'Settings and brand',
        'file' => 'settings.png',
        'alt' => 'Settings Appearance with logo upload and two brand colours',
        'lead' => 'Logo, two colours, TIN, bank, document prefix, your tax name and rate, and the currency you bill in. One design prints on every sheet. Only the company admin opens Settings.',
        'points' => [
            'Primary paints the desk and the strong bars. Accent marks rails, rules and highlights. Type on those colours is black or white, whichever reads.',
            'The sending mailbox is assigned by Vellisys. You can see the address. You cannot change the password.',
            'If a colour or logo is wrong, fix it here. Old documents keep the layout you pick now when you reprint.',
        ],
    ],
    [
        'id' => 'designs',
        'icon' => 'palette',
        'title' => 'Document designs',
        'file' => 'designs.png',
        'alt' => 'Document layouts including page borders, thermal roll, corner bill and watermarks',
        'lead' => 'Layouts live under Settings, Templates. Pick one and every quotation, invoice, receipt, expense and headed note reprints in that paper.',
        'points' => [
            'Corner bill and Accent bill put primary and accent triangles on the paper corners. Page frame and Inset border draw a rule around the A4 sheet. Thermal roll is 80mm for a receipt printer.',
            'Estate panel and Harbour block use solid colour bands - no fades, no washes - so they print cleanly. On a phone you see the same A4 sheet as print, scaled to fit.',
            'Changing the design here reprints the whole books, including letters. Letter text stays editable; only the paper around it changes.',
        ],
    ],
    [
        'id' => 'currency',
        'icon' => 'hash',
        'title' => 'Currency and rate',
        'file' => 'currency.png',
        'alt' => 'Settings tax with home currency and USD exchange rate',
        'lead' => 'The desk has a home currency. You can still issue a sheet in USD. Settings holds the rate: 1 USD equals n of your currency. Desk shows both on the greeting card.',
        'points' => [
            'Open Settings, Tax. Name the tax (VAT, GST, SST…) and the percent. Pick UGX, KES, EUR or type any three-letter code. Set how many of that currency equal one US dollar.',
            'Reports convert everything back to the home currency so the month still adds up.',
            'Printed sheets show the other currency underneath the total when a rate is set.',
        ],
    ],
    [
        'id' => 'people',
        'icon' => 'user',
        'title' => 'People on the desk',
        'file' => 'people.png',
        'alt' => 'Settings People with logins, titles and access',
        'lead' => 'Vellisys Start allows up to 2 users, Business up to 3, Pro up to 4. Vellisys sets how many this desk actually gets. Only the admin adds people.',
        'points' => [
            'Settings, People. Add a name, title, email, access and a temporary password.',
            'Books sees documents, clients, debtors, creditors and email. Sales sees quotations, invoices, receipts, clients and email. Neither opens Reports or Settings.',
            'Reset a password from the same list. People change their own password with Change my password on that page.',
        ],
    ],
    [
        'id' => 'password',
        'icon' => 'lock',
        'title' => 'Password',
        'file' => 'password.png',
        'alt' => 'Password page to change the signed-in login',
        'lead' => 'Anyone on the desk can change their own password. The company admin can also reset passwords under Settings, People.',
        'points' => [
            'Open Change my password from Settings, People. Enter the current password, then the new one twice. At least 8 characters.',
            'This is your login, not the company sending mailbox. Outgoing mail still leaves as the company.',
            'If you forget it, the company admin resets it. Super admin can also reach the desk if the company is locked out.',
        ],
    ],
    [
        'id' => 'install',
        'icon' => 'download',
        'title' => 'Install the app',
        'file' => 'install.png',
        'alt' => 'Current Vellisys sign-in page, where you install the app on a phone or a computer',
        'lead' => 'Put Vellisys on a phone or a computer from the Sign in page. The installed app opens on login, not the public website.',
        'points' => [
            'Open Sign in in the browser and stay on that page while you install. The app should start there. The public landing page stays on the website. Books stay behind the login.',
            'On a computer in Chrome or Edge, use the install icon in the address bar, or Install app in the browser menu. On a Mac in Safari: File, then Add to Dock.',
            'On Android, use Install app or Add to Home screen in the browser menu. On iPhone or iPad, open Sign in in Safari, tap Share, then Add to Home Screen.',
            'Tap the new Vellisys icon. You land on Sign in. From there you open the desk the same way as in the browser.',
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
  <div class="actions">
    <a class="btn" href="<?= h(url('document_new.php?kind=quotation')) ?>"><?= icon('quotation', 16) ?>New quotation</a>
  </div>
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
      <p class="tut-desc"><?= h($lesson['lead']) ?></p>
      <?php foreach ($lesson['points'] as $point): ?>
        <p class="tut-desc"><?= h($point) ?></p>
      <?php endforeach; ?>
    </div>
    <?= $shot($lesson['file'], $lesson['alt']) ?>
  </article>
<?php endforeach; ?>

<div class="tut-help card">
  <h2><?= icon('phone', 16) ?>Still stuck?</h2>
  <p class="lede">Call <?= product_phone_links_html() ?>, or write <?= product_email_link_html() ?>. The same mailbox that welcomed you will answer.</p>
</div>
<?php layout_end(); ?>
