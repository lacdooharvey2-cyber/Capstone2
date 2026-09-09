<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$contacts = $conn->query("
  SELECT DISTINCT u.user_id, u.name, u.role,
         (SELECT content FROM messages
          WHERE (sender_id=u.user_id AND receiver_id=$user_id)
             OR (sender_id=$user_id AND receiver_id=u.user_id)
          ORDER BY sent_at DESC LIMIT 1) AS last_msg,
         (SELECT status FROM messages
          WHERE (sender_id=u.user_id AND receiver_id=$user_id)
             OR (sender_id=$user_id AND receiver_id=u.user_id)
          ORDER BY sent_at DESC LIMIT 1) AS last_status
  FROM users u
  WHERE u.user_id <> $user_id AND u.role IN ('Admin','Customer','Technician')
  ORDER BY u.role, u.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cashier Messages - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include("navbarcashier.php"); ?>
  <div class="container-fluid mt-3">
    <div class="row">
      <div class="col-3 border-end bg-light">
        <h5 class="mt-3 mb-2">Messages</h5>
        <ul class="list-group">
          <?php if ($contacts): while($c = $contacts->fetch_assoc()): ?>
            <li class="list-group-item d-flex align-items-center">
              <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center me-2" style="width:35px; height:35px;">
                <?= strtoupper(substr($c['name'],0,1)) ?>
              </div>
              <div class="flex-grow-1">
                <a href="cashiermessage.php?contact=<?= $c['user_id'] ?>" class="text-decoration-none fw-bold">
                  <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['role']) ?>)
                </a>
                <div class="small text-muted"><?= htmlspecialchars($c['last_msg'] ?? '') ?></div>
              </div>
              <?php if($c['last_status']=='Unread'): ?><span class="badge bg-warning">Unread</span><?php endif; ?>
            </li>
          <?php endwhile; endif; ?>
        </ul>
      </div>
      <div class="col-9">
        <?php if(isset($_GET['contact'])): ?>
          <div class="p-3">
            <h5>Conversation</h5>
            <div class="border rounded p-3 mb-3" style="height:400px; overflow-y:auto;">
              <?php
              $contact_id = intval($_GET['contact']);
              $msgs = $conn->query("
                SELECT * FROM messages
                WHERE (sender_id=$user_id AND receiver_id=$contact_id)
                   OR (sender_id=$contact_id AND receiver_id=$user_id)
                ORDER BY sent_at ASC
              ");
              if ($msgs && $msgs->num_rows > 0):
                while($m = $msgs->fetch_assoc()):
              ?>
                <div class="<?= $m['sender_id']==$user_id ? 'text-end' : 'text-start' ?>">
                  <p class="mb-1"><strong><?= $m['sender_id']==$user_id ? 'You' : 'Contact' ?>:</strong> <?= htmlspecialchars($m['content']) ?></p>
                  <small class="text-muted"><?= $m['sent_at'] ?></small>
                </div>
              <?php endwhile; else: ?>
                <p class="text-muted">No messages yet.</p>
              <?php endif; ?>
            </div>
            <form method="post" action="sendmessage.php">
              <input type="hidden" name="receiver_id" value="<?= $contact_id ?>">
              <input type="hidden" name="redirect" value="cashiermessage.php">
              <div class="input-group">
                <input type="text" name="content" class="form-control" placeholder="Type a message...">
                <button class="btn btn-danger">Send</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="text-center text-muted mt-5"><h4>Select a contact to start messaging</h4></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
