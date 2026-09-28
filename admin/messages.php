<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('dashboard.view');
$title = 'Website Enquiries';
$active = 'messages';

if (is_post()) {
    if (input('action') === 'read') q('UPDATE contact_messages SET is_read = 1 - is_read WHERE id=?', [input_int('id')]);
    redirect('admin/messages.php');
}
$rows = q_all('SELECT * FROM contact_messages ORDER BY is_read, created_at DESC LIMIT 200');
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <?php foreach ($rows as $m): ?>
    <div style="padding:12px 0;border-bottom:1px solid var(--line);<?= $m['is_read'] ? 'opacity:.65' : '' ?>">
      <div class="card-head" style="margin:0"><div><b><?= e($m['subject'] ?: 'Enquiry') ?></b> — <?= e($m['name']) ?> &lt;<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>&gt; <?= e($m['phone']) ?>
        <?= $m['is_read'] ? '' : '<span class="badge badge-red">new</span>' ?></div>
        <div class="btn-row"><span class="small muted"><?= fdatetime($m['created_at']) ?></span>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-ghost"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form></div></div>
      <p style="margin:6px 0 0;white-space:pre-line"><?= e($m['message']) ?></p>
    </div>
  <?php endforeach; if (!$rows): ?><p class="muted">No enquiries yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
