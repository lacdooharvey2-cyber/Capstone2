<?php
session_start();
include("db.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Messages - FixTrack Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include("navbaradmin.php"); ?>

  <div class="container-fluid mt-3">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-3 border-end bg-light">
      <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
        <h5>Messages</h5>
        <a href="newmessage.php" class="btn btn-sm btn-primary">+ New Message</a>
      </div>
      <input type="text" class="form-control mb-2" placeholder="Search customers/staff...">
      <div class="btn-group mb-2 w-100">
        <button class="btn btn-outline-secondary btn-sm">All</button>
        <button class="btn btn-outline-secondary btn-sm">Customers</button>
        <button class="btn btn-outline-secondary btn-sm">Staff</button>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="unrepliedToggle">
        <label class="form-check-label" for="unrepliedToggle">Show only unreplied</label>
      </div>
      <ul class="list-group">
        <?php
        $contacts = $conn->query("
          SELECT u.user_id, u.name, u.role,
                 (SELECT content FROM messages WHERE receiver_id=u.user_id ORDER BY sent_at DESC LIMIT 1) AS last_msg,
                 (SELECT status FROM messages WHERE receiver_id=u.user_id ORDER BY sent_at DESC LIMIT 1) AS last_status
          FROM users u ORDER BY u.role, u.name
        ");
        while($c = $contacts->fetch_assoc()):
        ?>
          <li class="list-group-item d-flex align-items-center">
            <!-- Avatar initials -->
            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center me-2" style="width:35px; height:35px;">
              <?= strtoupper(substr($c['name'],0,1)) ?>
            </div>
            <div class="flex-grow-1">
              <a href="adminmessages.php?contact=<?= $c['user_id'] ?>" class="text-decoration-none fw-bold">
                <?= htmlspecialchars($c['name']) ?> (<?= $c['role'] ?>)
              </a>
              <div class="small text-muted"><?= htmlspecialchars($c['last_msg']) ?></div>
            </div>
            <?php if($c['last_status']=='Unreplied'): ?>
              <span class="badge bg-warning">Unreplied</span>
            <?php endif; ?>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>

    <!-- Conversation Panel -->
    <div class="col-9">
      <?php if(isset($_GET['contact'])): ?>
        <div class="p-3">
          <h5>Conversation</h5>
          <div class="border rounded p-3 mb-3" style="height:400px; overflow-y:auto;">
            <?php
            $contact_id = intval($_GET['contact']);
            $msgs = $conn->query("
              SELECT * FROM messages 
              WHERE (sender_id={$_SESSION['user_id']} AND receiver_id=$contact_id) 
                 OR (sender_id=$contact_id AND receiver_id={$_SESSION['user_id']}) 
              ORDER BY sent_at ASC
            ");
            if ($msgs->num_rows > 0):
              while($m = $msgs->fetch_assoc()):
            ?>
              <div class="<?= $m['sender_id']==$_SESSION['user_id'] ? 'text-end' : 'text-start' ?>">
                <p class="mb-1"><strong><?= $m['sender_id']==$_SESSION['user_id'] ? 'You' : 'Contact' ?>:</strong> 
                <?= htmlspecialchars($m['content']) ?></p>
                <small class="text-muted"><?= $m['sent_at'] ?></small>
              </div>
            <?php endwhile; else: ?>
              <p class="text-muted">No messages yet.</p>
            <?php endif; ?>
          </div>
          <form method="post" action="sendmessage.php">
            <input type="hidden" name="receiver_id" value="<?= $contact_id ?>">
            <input type="hidden" name="redirect" value="adminmessages.php">
            <div class="input-group">
              <input type="text" name="content" class="form-control" placeholder="Type a message...">
              <button class="btn btn-danger">Send</button>
            </div>
          </form>
        </div>
      <?php else: ?>
        <div class="text-center text-muted mt-5">
          <h4>Select a contact to start messaging</h4>
          <p>Choose a customer or staff from the list to begin a conversation.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

