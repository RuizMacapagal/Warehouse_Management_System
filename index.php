<?php
// Simple landing page to choose between full system login or mock demo
if (isset($_GET['demo'])) {
    header('Location: anything/mocksys.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Warehouse Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
  <div class="card shadow" style="width: 480px;">
    <div class="card-body">
      <h3 class="card-title mb-3">Welcome</h3>
      <p class="text-muted">Choose how you want to run the demo:</p>
      <div class="d-grid gap-2 mt-3">
        <a class="btn btn-danger" href="index.php?demo=1">Launch Mock Demo (Client-side)</a>
        <a class="btn btn-secondary" href="login.php">Go to Login (Full System)</a>
      </div>
      <p class="mt-3 text-muted" style="font-size: 12px;">The mock demo runs fully in your browser using localStorage. The full system requires MySQL setup.</p>
    </div>
  </div>
</body>
</html>
