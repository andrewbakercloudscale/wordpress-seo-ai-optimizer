<?php
/**
 * GET  /manage?key=<license_key>            — subscription management page
 * GET  /manage?session=<token>&activated=1  — post-checkout return URL
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = db();

// Resolve key from session param (post-checkout return)
$key     = preg_replace('/[^a-f0-9]/', '', $_GET['key'] ?? '');
$session = preg_replace('/[^a-f0-9]/', '', $_GET['session'] ?? '');

if (!$key && $session) {
    $stmt = $db->prepare("SELECT license_key FROM licenses WHERE session_token = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$session]);
    $row = $stmt->fetch();
    $key = $row['license_key'] ?? '';
}

$row = null;
if ($key) {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch() ?: null;
}

$status     = $row['status']            ?? '';
$usage      = (int)($row['monthly_requests'] ?? 0);
$limit      = (int)($row['monthly_limit']    ?? 200);
$reset      = $row['usage_reset_date']  ?? '';
$reset_next = $reset ? date('d M Y', strtotime('+1 month', strtotime($reset))) : '—';
$pct        = $limit > 0 ? min(100, round($usage / $limit * 100)) : 0;
$bar_color  = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#22c55e');
$activated  = !empty($_GET['activated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Subscription — CloudScale SEO AI</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.1);max-width:480px;width:100%;overflow:hidden}
  .header{background:#1e293b;color:#fff;padding:20px 24px;display:flex;align-items:center;gap:12px}
  .header h1{font-size:16px;font-weight:600}
  .header .logo{font-size:24px}
  .body{padding:24px}
  .badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:16px}
  .badge.active{background:#dcfce7;color:#15803d}
  .badge.past_due{background:#fef9c3;color:#854d0e}
  .badge.cancelled{background:#f1f5f9;color:#64748b}
  .badge.pending{background:#dbeafe;color:#1d4ed8}
  .row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;color:#374151}
  .row strong{color:#111}
  .bar-wrap{background:#e5e7eb;border-radius:4px;height:8px;overflow:hidden;margin-bottom:20px}
  .bar{height:100%;border-radius:4px;transition:width .4s}
  .btn{display:inline-block;padding:10px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-align:center;width:100%}
  .btn-danger{background:#ef4444;color:#fff}
  .btn-danger:hover{background:#dc2626}
  .btn-cancel{background:#f1f5f9;color:#374151;margin-top:8px}
  .btn-cancel:hover{background:#e2e8f0}
  .alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
  .alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
  .alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
  #confirm-section{display:none;margin-top:16px;padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px}
  p.note{font-size:12px;color:#6b7280;margin-top:16px;line-height:1.6}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <span class="logo">⚡</span>
    <h1>CloudScale SEO AI — Managed API</h1>
  </div>
  <div class="body">

  <?php if ($activated && $status === 'active'): ?>
    <div class="alert alert-success">✓ Subscription activated! Your license key has been sent to your plugin automatically.</div>
  <?php endif; ?>

  <?php if (!$row): ?>
    <p style="color:#6b7280;font-size:14px">No subscription found. Please return to your WordPress plugin to subscribe.</p>

  <?php elseif ($status === 'active'): ?>
    <span class="badge active">✓ Active</span>
    <div class="row"><span>Email</span><strong><?php echo htmlspecialchars($row['email']); ?></strong></div>
    <div class="row"><span>Requests used</span><strong><?php echo "{$usage} / {$limit}"; ?></strong></div>
    <div class="bar-wrap"><div class="bar" style="width:<?php echo $pct; ?>%;background:<?php echo $bar_color; ?>"></div></div>
    <div class="row"><span>Resets</span><strong><?php echo htmlspecialchars($reset_next); ?></strong></div>

    <p style="font-size:13px;color:#374151;margin-bottom:16px">Your subscription renews monthly at <strong>R69/month</strong>. PayFast will debit your card automatically.</p>

    <button class="btn btn-danger" onclick="document.getElementById('confirm-section').style.display='block';this.style.display='none'">
      Cancel subscription
    </button>

    <div id="confirm-section">
      <p style="font-size:13px;color:#b91c1c;margin-bottom:12px;font-weight:600">Are you sure? You will lose access to AI features at the end of the current billing period.</p>
      <button class="btn btn-danger" id="confirm-cancel-btn" onclick="doCancel()">Yes, cancel my subscription</button>
      <button class="btn btn-cancel" onclick="document.getElementById('confirm-section').style.display='none';document.querySelector('.btn-danger:not(#confirm-cancel-btn)').style.display=''">
        Keep subscription
      </button>
      <p id="cancel-msg" style="display:none;margin-top:10px;font-size:13px"></p>
    </div>

  <?php elseif ($status === 'past_due'): ?>
    <span class="badge past_due">⚠ Payment failed</span>
    <p style="font-size:13px;color:#374151;margin-bottom:16px">Your last payment failed. PayFast will retry automatically. If the problem persists, please contact <a href="mailto:support@andrewbaker.ninja">support@andrewbaker.ninja</a>.</p>

  <?php elseif ($status === 'cancelled'): ?>
    <span class="badge cancelled">Cancelled</span>
    <p style="font-size:13px;color:#6b7280">Your subscription has been cancelled. Return to your WordPress plugin to resubscribe.</p>

  <?php else: ?>
    <span class="badge pending">Activating…</span>
    <p style="font-size:13px;color:#6b7280">Your subscription is being activated. This usually takes a few seconds — please check back shortly.</p>
  <?php endif; ?>

  <p class="note">Questions? Email <a href="mailto:support@andrewbaker.ninja">support@andrewbaker.ninja</a></p>
  </div>
</div>

<?php if ($row && $status === 'active'): ?>
<script>
function doCancel() {
    var btn = document.getElementById('confirm-cancel-btn');
    var msg = document.getElementById('cancel-msg');
    btn.disabled = true;
    btn.textContent = 'Cancelling…';
    fetch('/cancel', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({key: '<?php echo addslashes($key); ?>', confirm: 'yes'})
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        msg.style.display = '';
        if (data.ok) {
            msg.style.color = '#15803d';
            msg.textContent = '✓ Subscription cancelled. You will not be charged again.';
            btn.style.display = 'none';
            document.querySelector('.badge').textContent = 'Cancelled';
            document.querySelector('.badge').className = 'badge cancelled';
        } else {
            msg.style.color = '#b91c1c';
            msg.textContent = data.error || 'Cancellation failed. Please contact support.';
            btn.disabled = false;
            btn.textContent = 'Yes, cancel my subscription';
        }
    })
    .catch(function() {
        msg.style.display = '';
        msg.style.color = '#b91c1c';
        msg.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Yes, cancel my subscription';
    });
}
</script>
<?php endif; ?>
</body>
</html>
