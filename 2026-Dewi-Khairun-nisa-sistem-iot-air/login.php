<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    // Hardcoded admin login
    if ($user === 'admin' && $pass === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Login - Sistem Kualitas Air</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
     /* Background bubbles for login */
     .bubble {
       position: absolute;
       border-radius: 50%;
       background: rgba(13, 148, 136, 0.2);
       filter: blur(40px);
       z-index: -1;
     }
     .b1 { width: 400px; height: 400px; top: -100px; left: -100px; }
     .b2 { width: 500px; height: 500px; bottom: -150px; right: -100px; background: rgba(6, 182, 212, 0.15); }
     
     /* Shake animation for error */
     @keyframes shake {
       0%, 100% { transform: translateX(0); }
       25% { transform: translateX(-10px); }
       75% { transform: translateX(10px); }
     }
     .shake { animation: shake 0.4s ease-in-out; }
  </style>
</head>
<body style="justify-content: center; align-items: center; position: relative;">

  <!-- Ambient background -->
  <div class="bubble b1"></div>
  <div class="bubble b2"></div>

  <!-- Theme Toggle -->
  <div class="theme-toggle" id="theme-toggle" title="Ganti Tema" style="position: absolute; top: 2rem; right: 2rem;">☀️</div>

  <div class="login-container <?php if($error) echo 'shake'; ?>">
    <div style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
    <h1 style="margin-bottom: 0.5rem; font-family: 'Outfit';">AquaMonitor</h1>
    <p style="color: var(--text-secondary); margin-bottom: 2rem;">Masuk ke Dashboard IoT</p>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-field">
        <input type="text" name="username" id="username" placeholder=" " required autocomplete="off">
        <label for="username">Username</label>
      </div>
      
      <div class="form-field">
        <input type="password" name="password" id="password" placeholder=" " required>
        <label for="password">Password</label>
        <span class="toggle-password" id="toggle-btn" title="Intip Password" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); cursor:pointer; color: var(--text-secondary);">
          <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path id="eye-path" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"></path>
          </svg>
        </span>
      </div>
      
      <button type="submit" class="btn-login">Masuk</button>
    </form>
  </div>

  <script src="assets/theme.js"></script>
  <script>
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('toggle-btn');
    const eyeIcon = document.getElementById('eye-icon');
    const eyePath = document.getElementById('eye-path');

    // Data path SVG untuk kondisi mata terbuka
    const eyeOpenPath = 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z';
    
    // Data path SVG untuk kondisi mata tertutup / dicoret
    const eyeClosedPath = 'M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.82l2.92 2.92c1.51-1.26 2.7-2.89 3.44-4.74-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16c.56-.23 1.17-.36 1.82-.36zm-6.36.13L3.84 5.33 2.41 6.76l2.85 2.85C4.26 10.4 3.45 11.15 2.84 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l2.42 2.42 1.41-1.41L7.05 8.56 5.64 7.13zM12 17c-2.76 0-5-2.24-5-5 0-.77.18-1.5.49-2.14l7.65 7.65c-.64.31-1.37.49-2.14.49zm-.72-8.32L14.32 11.7c-.1-.94-.86-1.7-1.8-1.8z';

    toggleButton.addEventListener('click', function () {
        // Tukar type attribute antara 'password' dan 'text'
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Ganti bentuk garis SVG secara dinamis
        if (type === 'password') {
            eyePath.setAttribute('d', eyeOpenPath);
        } else {
            eyePath.setAttribute('d', eyeClosedPath);
        }
    });
  </script>
</body>
</html>
