<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KakaiOne | Staff Login</title>
  <?php include __DIR__ . '/../includes/links.php'; ?>
</head>

<body class="login-page">
  <div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="login-card text-center">
      <div class="brand">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo">
        <h3>KakaiOne</h3>
        <p class="text-muted small mb-0">Staff Login</p>
      </div>

      <form id="loginForm" class="mt-3">
        <div class="mb-3 text-start">
          <label for="username" class="form-label">Username</label>
          <input type="text" id="username" class="form-control" placeholder="Enter username" required>
        </div>

        <div class="mb-3 text-start">
          <label for="password" class="form-label">Password</label>
          <input type="password" id="password" class="form-control" placeholder="Enter password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3">Login</button>
      </form>

      <p id="errorMsg" class="text-danger mt-3 small"></p>
      <p class="footer-text mt-4">© 2025 KakaiOne | All rights reserved.</p>
    </div>
  </div>

  <script>
    const form = document.getElementById("loginForm");
    const errorMsg = document.getElementById("errorMsg");

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const username = document.getElementById("username").value.trim();
      const password = document.getElementById("password").value.trim();

      if (!username || !password) {
        errorMsg.textContent = "Please enter both username and password.";
        return;
      }

      errorMsg.textContent = "";

      try {
        const res = await fetch("../../backend/auth/login.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            username,
            password
          })
        });

        const data = await res.json();

        if (data.success) {
          Swal.fire({
            icon: "success",
            title: "Welcome!",
            text: data.message,
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            window.location.href = data.redirect;
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Login Failed",
            text: data.message || "Invalid credentials.",
          });
        }
      } catch (error) {
        Swal.fire("Error", "Unable to connect to the server.", "error");
      }
    });
  </script>
</body>

</html>