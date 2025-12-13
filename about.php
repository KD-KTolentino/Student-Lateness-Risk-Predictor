<?php
// about.php
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About Us</title>
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
        <a href="index.php">Home</a>
        <a href="predict.php">Lateness Risk Predictor</a>
        <a href="about.php" class="active">About Us</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <h1>About Us</h1>
    <p class="subtitle">A course project developed for ITEP 308 – System Integration and Architecture</p>

    <div class="info-section">
      <h2>Project Overview</h2>
      <p><strong>Course:</strong> ITEP 308 – System Integration and Architecture</p>
      <p><strong>Academic Year:</strong> 2025–2026</p>
      <p><strong>Class:</strong> 3WMAD2</p>
      <p><strong>Project Goal:</strong> To integrate a basic machine learning model into a web-based tool that supports student time management and punctuality.</p>
    </div>

    <div class="info-section">
      <h2>Student Lateness Risk Predictor</h2>
      <p class="description">
        The Student Lateness Risk Predictor is designed to help students understand how aspects of their daily routines relate to the likelihood of arriving late. By entering information such as typical sleep duration, travel time, wake-up schedule, first class schedule, and recent lateness history, users receive a qualitative risk level (Low, Medium, or High) together with tailored recommendations.
      </p>
      <p class="description">
        The emphasis of this tool is personal reflection and self-improvement. It is intended to encourage students to examine their habits, explore "what-if" scenarios, and consider adjustments that may improve punctuality.
      </p>
      <p class="description">
        The public demonstration of this system relies on synthetic or example data to illustrate model behavior. It is not designed for high-stakes decision-making, grading, or institutional monitoring of individual students.
      </p>
    </div>

    <div class="info-section">
      <h2>How the Predictor Works</h2>
      <p class="description">
        The application collects a small set of input features related to a student's routine:
      </p>
      <ul class="tech-list">
        <li>Typical hours of sleep per night</li>
        <li>Estimated minimum and maximum travel time to class</li>
        <li>Usual wake-up time</li>
        <li>Time of the first scheduled class or commitment</li>
        <li>Number of recent late arrivals</li>
      </ul>
      <p class="description">
        These inputs are normalized and passed to a K-Nearest Neighbors (KNN) classifier implemented with PHP-ML. The classifier outputs a lateness risk category (Low, Medium, or High). Based on this category and the specific pattern of inputs, the system then generates concise, practical advice to support improved time management.
      </p>
    </div>

    <div class="info-section">
      <h2>Team Members</h2>
      <div class="team-grid">
        <div class="team-member">
          <strong>Tolentino, Kenneth Ace P.</strong>
          <span class="role">Developer</span>
        </div>
        <div class="team-member">
          <strong>Verana, Riz Ivan G.</strong>
          <span class="role">Designer</span>
        </div>
        <div class="team-member">
          <strong>Manalo, Rui P.</strong>
          <span class="role">Project Lead</span>
        </div>
      </div>
      <p class="description">
        This project was collaboratively developed as part of the requirements for ITEP 308, with an emphasis on applying system integration concepts and basic machine learning techniques to a realistic student-centered scenario.
      </p>
    </div>

    <div class="info-section">
      <h2>Technology Stack</h2>
      <ul class="tech-list">
        <li>PHP 7.4+ for backend logic</li>
        <li>PHP-ML (Machine Learning library) for KNN classification</li>
        <li>HTML5 &amp; CSS3 for responsive design</li>
        <li>CSV-based data training for model accuracy</li>
      </ul>
    </div>

    <div class="info-section">
      <h2>Limitations and Ethical Considerations</h2>
      <p class="description">
        The predictions provided by this application are approximate and depend on the quality and representativeness of the underlying example data. They should not be interpreted as precise measurements of student performance or behavior.
      </p>
      <p class="description">
        The tool is intended solely for educational and exploratory purposes. It is not designed to replace professional judgment, formal academic policies, or institutional systems for attendance monitoring.
      </p>
    </div>

    <a href="index.php" class="back-link">← Back to Home</a>
  </div>

  <footer class="site-footer">
    <p>© 2025 SLRP – Student Lateness Risk Predictor. Developed for ITEP 308, 3WMAD2.</p>
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
