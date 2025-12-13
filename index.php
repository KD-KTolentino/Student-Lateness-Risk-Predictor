<?php
// index.php
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Lateness Risk Predictor</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <div class="nav-brand">
      <span class="nav-logo">⏰</span>
      <div class="nav-text">
        <span class="nav-title">SLRP</span>
        <span class="nav-subtitle">Student Lateness Risk Predictor</span>
      </div>
    </div>
    <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <div class="nav-links">
      <a href="index.php" class="active">Home</a>
      <a href="predict.php">Lateness Risk Predictor</a>
      <a href="about.php">About Us</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1>Student Lateness Risk Predictor</h1>
  <p class="subtitle">A web-based decision support tool that estimates a student's likelihood of arriving late based on their daily routine.</p>
  
  <div class="hero-section">
    <p class="hero-text">
      This application uses a simple machine learning model to relate everyday habits—such as sleep duration, travel time, wake-up schedule, first class, and recent lateness history—to an overall lateness risk level. It is intended to support reflection on time management and to encourage more punctual routines.
    </p>
  </div>

  <div class="features-grid">
    <div class="feature-card">
      <h3>📊 Data-Driven Estimates</h3>
      <p>Provides lateness risk estimates using patterns learned from example data, based on your sleep, travel time, and schedule.</p>
    </div>
    <div class="feature-card">
      <h3>⏰ Morning Routine Insight</h3>
      <p>Highlights how your wake-up time, commute, and first scheduled class interact and influence your punctuality.</p>
    </div>
    <div class="feature-card">
      <h3>💡 Actionable Recommendations</h3>
      <p>Offers concise, practical suggestions that students can try in order to reduce their risk of arriving late.</p>
    </div>
  </div>

  <div class="cta-section">
    <p>Estimate your current lateness risk based on your daily routine.</p>
    <a href="predict.php"><button>Start Prediction</button></a>
  </div>

  <a href="about.php" class="back-link">Learn more about the project and methodology →</a>
</div>

<footer class="site-footer">
  <p> 2025 SLRP – Student Lateness Risk Predictor. Developed for ITEP 308, 3WMAD2.</p>
</footer>

<script>
  (function() {
    var toggle = document.querySelector('.nav-toggle');
    var links = document.querySelector('.nav-links');
    if (toggle && links) {
      toggle.addEventListener('click', function () {
        var isOpen = links.classList.toggle('nav-links-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }
  })();
</script>

</body>
</html>
