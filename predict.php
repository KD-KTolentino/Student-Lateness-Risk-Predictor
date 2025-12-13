<?php
session_start();
require 'vendor/autoload.php';
use Phpml\Classification\KNearestNeighbors;
use Phpml\ModelManager;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$groqApiKey = $_ENV['GROQ_API_KEY'];

// Feature normalization bounds (min-max)
// These represent the expected ranges in the training data
$FEATURE_BOUNDS = [
    'sleep' => ['min' => 4.0, 'max' => 9.0],
    'travel' => ['min' => 5, 'max' => 80],
    'wake' => ['min' => 5, 'max' => 8],
    'first_class' => ['min' => 7, 'max' => 11],
    'prev_lates' => ['min' => 0, 'max' => 6]
];

// Min-max normalization: (x - min) / (max - min) => [0, 1]
function normalize_feature($value, $min, $max) {
    if ($max === $min) return 0.5; // edge case: constant feature
    return ($value - $min) / ($max - $min);
}


// Load CSV data with normalization
function loadTrainingData() {
    global $FEATURE_BOUNDS;
    $csvFile = __DIR__ . '/train.csv';
    $rows = array_map('str_getcsv', file($csvFile));
    $header = array_shift($rows);

    $samples = [];
    $labels = [];

    foreach ($rows as $r) {
        // 5 features: sleep_hours, travel_mins, wake_hour, first_class_hour, prev_lates
        $sleep_norm = normalize_feature((float)$r[0], $FEATURE_BOUNDS['sleep']['min'], $FEATURE_BOUNDS['sleep']['max']);
        $travel_norm = normalize_feature((float)$r[1], $FEATURE_BOUNDS['travel']['min'], $FEATURE_BOUNDS['travel']['max']);
        $wake_norm = normalize_feature((float)$r[2], $FEATURE_BOUNDS['wake']['min'], $FEATURE_BOUNDS['wake']['max']);
        $first_class_norm = normalize_feature((float)$r[3], $FEATURE_BOUNDS['first_class']['min'], $FEATURE_BOUNDS['first_class']['max']);
        $prev_lates_norm = normalize_feature((float)$r[4], $FEATURE_BOUNDS['prev_lates']['min'], $FEATURE_BOUNDS['prev_lates']['max']);
        
        $samples[] = [$sleep_norm, $travel_norm, $wake_norm, $first_class_norm, $prev_lates_norm];
        $labels[] = trim($r[5]);
    }
    return [$samples, $labels];
}

 $prediction = null;
 $advice = "";
 $adviceList = [];
 $inputValues = [];
 $errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Load persisted model; if missing, train and save it (first-run)
    $modelDir = __DIR__ . '/model';
    $modelFile = $modelDir . '/model_knn.dat';
    $manager = new ModelManager();

    if (!file_exists($modelFile)) {
        // First run: train model and save it
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }
        list($samples, $labels) = loadTrainingData();
        $knn = new KNearestNeighbors();
        $knn->train($samples, $labels);
        $manager->saveToFile($knn, $modelFile);
    } else {
        // Load saved model
        $knn = $manager->restoreFromFile($modelFile);
    }

    // Validate and parse inputs
    $errorMessage = '';

    $sleep = isset($_POST['sleep']) ? floatval($_POST['sleep']) : null;
    $travel_min_hours = isset($_POST['travel_min_hours']) ? intval($_POST['travel_min_hours']) : 0;
    $travel_min_mins = isset($_POST['travel_min_mins']) ? intval($_POST['travel_min_mins']) : 0;
    $travel_max_hours = isset($_POST['travel_max_hours']) ? intval($_POST['travel_max_hours']) : 0;
    $travel_max_mins = isset($_POST['travel_max_mins']) ? intval($_POST['travel_max_mins']) : 0;
    $wake_hour_12 = isset($_POST['wake_hour']) ? intval($_POST['wake_hour']) : null;
    $wake_mins = isset($_POST['wake_mins']) ? intval($_POST['wake_mins']) : null;
    $wake_ampm = isset($_POST['wake_ampm']) ? $_POST['wake_ampm'] : null;
    // First scheduled activity (first class) inputs
    $first_class_hour_12 = isset($_POST['first_class_hour']) ? intval($_POST['first_class_hour']) : null;
    $first_class_mins = isset($_POST['first_class_mins']) ? intval($_POST['first_class_mins']) : null;
    $first_class_ampm = isset($_POST['first_class_ampm']) ? $_POST['first_class_ampm'] : null;
    $lateCount = isset($_POST['lateCount']) ? floatval($_POST['lateCount']) : null;

    // Basic validation
    if ($sleep === null || $sleep < 0 || $sleep > 24) {
        $errorMessage = 'Please enter a valid sleep hours value (0–24).';
    } elseif ($travel_min_hours < 0 || $travel_min_hours > 10) {
        $errorMessage = 'Please enter valid minimum travel hours (0–10).';
    } elseif ($travel_min_mins < 0 || $travel_min_mins > 59) {
        $errorMessage = 'Please enter valid minimum travel minutes (0–59).';
    } elseif ($travel_max_hours < 0 || $travel_max_hours > 10) {
        $errorMessage = 'Please enter valid maximum travel hours (0–10).';
    } elseif ($travel_max_mins < 0 || $travel_max_mins > 59) {
        $errorMessage = 'Please enter valid maximum travel minutes (0–59).';
    } elseif ($wake_hour_12 === null || $wake_hour_12 < 1 || $wake_hour_12 > 12) {
        $errorMessage = 'Please enter a valid wake hour (1–12).';
    } elseif ($wake_mins === null || $wake_mins < 0 || $wake_mins > 59) {
        $errorMessage = 'Please enter valid wake minutes (0–59).';
    } elseif ($wake_ampm !== 'AM' && $wake_ampm !== 'PM') {
        $errorMessage = 'Please choose AM or PM for wake time.';
    } elseif ($first_class_hour_12 === null || $first_class_hour_12 < 1 || $first_class_hour_12 > 12) {
        $errorMessage = 'Please enter a valid first-class hour (1–12).';
    } elseif ($first_class_mins === null || $first_class_mins < 0 || $first_class_mins > 59) {
        $errorMessage = 'Please enter valid first-class minutes (0–59).';
    } elseif ($first_class_ampm !== 'AM' && $first_class_ampm !== 'PM') {
        $errorMessage = 'Please choose AM or PM for first-class time.';
    } elseif ($lateCount === null || $lateCount < 0) {
        $errorMessage = 'Please enter a valid past lateness count (>= 0).';
    }

    if ($errorMessage === '') {
        // Convert travel hours + minutes to total minutes for each range bound
        $travel_min_total = ($travel_min_hours * 60) + $travel_min_mins;
        $travel_max_total = ($travel_max_hours * 60) + $travel_max_mins;
        
        // Ensure min <= max
        if ($travel_max_total < $travel_min_total) {
            $tmp = $travel_min_total; $travel_min_total = $travel_max_total; $travel_max_total = $tmp;
        }
        
        $travel_avg = ($travel_min_total + $travel_max_total) / 2.0;

        // convert 12-hour + minutes + AM/PM to 0-24 (decimal format for minute precision)
        if ($wake_ampm === 'AM') {
            $wake24_hour = ($wake_hour_12 === 12) ? 0 : $wake_hour_12;
        } else {
            $wake24_hour = ($wake_hour_12 === 12) ? 12 : ($wake_hour_12 + 12);
        }
        $wake24 = $wake24_hour + ($wake_mins / 60.0); // e.g., 7:30 AM = 7.5

        // convert first-class time to decimal hours (0-24)
        if ($first_class_ampm === 'AM') {
            $first_class_hour24 = ($first_class_hour_12 === 12) ? 0 : $first_class_hour_12;
        } else {
            $first_class_hour24 = ($first_class_hour_12 === 12) ? 12 : ($first_class_hour_12 + 12);
        }
        $first_class_decimal = $first_class_hour24 + ($first_class_mins / 60.0);

        // Build feature vector (5 features: sleep_hours, travel_mins, wake_hour, first_class_hour, prev_lates)
        // Normalize each feature to [0, 1] range for fair KNN distance calculation
        $sleep_norm = normalize_feature($sleep, $FEATURE_BOUNDS['sleep']['min'], $FEATURE_BOUNDS['sleep']['max']);
        $travel_norm = normalize_feature($travel_avg, $FEATURE_BOUNDS['travel']['min'], $FEATURE_BOUNDS['travel']['max']);
        $wake_norm = normalize_feature($wake24, $FEATURE_BOUNDS['wake']['min'], $FEATURE_BOUNDS['wake']['max']);
        $first_class_norm = normalize_feature($first_class_decimal, $FEATURE_BOUNDS['first_class']['min'], $FEATURE_BOUNDS['first_class']['max']);
        $prev_lates_norm = normalize_feature($lateCount, $FEATURE_BOUNDS['prev_lates']['min'], $FEATURE_BOUNDS['prev_lates']['max']);
        
        $sample = [$sleep_norm, $travel_norm, $wake_norm, $first_class_norm, $prev_lates_norm];
        $prediction = $knn->predict($sample);

        // Save input values for the summary display
        $inputValues = [$sleep, $travel_avg, $wake24, $lateCount, $first_class_decimal, $first_class_hour_12, $first_class_mins, $first_class_ampm];

        

        // --- Custom lateness risk logic ---
$buffer_hours = isset($first_class_decimal) && isset($wake24) ? ($first_class_decimal - $wake24) : null;
$buffer_minutes = ($buffer_hours !== null) ? $buffer_hours * 60 : null;

$very_low_sleep = ($sleep !== null && $sleep < 6.0);
$recent_lateness = ($lateCount !== null && $lateCount >= 2);
$short_buffer = ($buffer_minutes !== null && $buffer_minutes < 30);
$long_commute = ($travel_avg !== null && $travel_avg > 60);

$red_flags = 0;
if ($very_low_sleep) $red_flags++;
if ($recent_lateness) $red_flags++;
if ($short_buffer) $red_flags++;
if ($long_commute) $red_flags++;

if ($very_low_sleep && $buffer_hours !== null && $buffer_hours > 2) {
    // Downgrade risk if buffer is large
    $prediction = ($red_flags >= 2) ? 'Medium' : 'Low';
} elseif ($red_flags >= 2) {
    $prediction = 'High';
} elseif ($very_low_sleep || $recent_lateness || $short_buffer || $long_commute) {
    $prediction = 'Medium';
} else {
    $prediction = 'Low';
}

// Header based on prediction
if ($prediction === 'High') {
    $adviceList[] = '<strong style="color:#9b1c1c;">HIGH RISK:</strong> Immediate adjustments suggested to avoid further lateness.';
} elseif ($prediction === 'Medium') {
    $adviceList[] = '<strong style="color:#854d0e;">MODERATE RISK:</strong> A few targeted changes can reduce your risk.';
} else {
    $adviceList[] = '<strong style="color:#065f46;">LOW RISK:</strong> Your routine looks solid — consider small improvements to stay consistent.';
}


        // Priority scoring (higher = more impactful)
        $priorities = [];

        // Sleep suggestions
        if ($sleep < 6.0) {
            $priorities['sleep'] = 4;
            $target = max(7.0, $sleep + 1.0);
            $advice_sleep = sprintf("You average %.1f hours of sleep. Aim for %.1f–8.0 hours by going to bed 30–60 minutes earlier.", $sleep, $target);
        } elseif ($sleep < 7.0) {
            $priorities['sleep'] = 2;
            $advice_sleep = sprintf("You average %.1f hours. Increasing to 7–8 hours improves alertness and timeliness.", $sleep);
        } else {
            $priorities['sleep'] = 0;
            $advice_sleep = "Sleep duration looks reasonable — keep a consistent bedtime.";
        }

        // Past lateness pattern
        if ($lateCount >= 4) {
            $priorities['history'] = 4;
            $advice_history = "You've been late several times recently — create a buffer of 15–30 minutes and try a dry run of your morning routine to identify bottlenecks.";
        } elseif ($lateCount >= 2) {
            $priorities['history'] = 2;
            $advice_history = "Some recent lateness — tighten one part of your routine (wake time or departure buffer) this week to break the pattern.";
        } else {
            $priorities['history'] = 0;
            $advice_history = "No strong lateness pattern — keep routines consistent.";
        }

        // Travel suggestions (use average travel_avg)
        if ($travel_avg > 120) {
            $priorities['travel'] = 4;
            $advice_travel = "Very long commute — consider earlier departure, alternative routes, or remote/shift options if available.";
        } elseif ($travel_avg > 60) {
            $priorities['travel'] = 3;
            $advice_travel = "Long commute — leave 15–30 minutes earlier or prepare the night before to save time in the morning.";
        } elseif ($travel_avg > 30) {
            $priorities['travel'] = 1;
            $advice_travel = "Moderate commute — aim for consistent departure times and small buffer minutes to reduce variability.";
        } else {
            $priorities['travel'] = 0;
            $advice_travel = "Short commute — small routine checks should keep you on time.";
        }

        // Wake time suggestions
        $wake_label = '';
        if ($wake24 >= 9.0) {
            $priorities['wake'] = 3;
            $wake_label = 'late';
            $advice_wake = "You wake fairly late — consider shifting your wake time earlier by 20–40 minutes and adjusting bedtime accordingly.";
        } elseif ($wake24 > 7.5) {
            $priorities['wake'] = 2;
            $wake_label = 'slightly late';
            $advice_wake = "Waking after 7:30 can compress your morning—try a 15-minute earlier wakeup to reduce rush.";
        } elseif ($wake24 < 5.5) {
            $priorities['wake'] = 1;
            $wake_label = 'early';
            // Only warn if waking >2 hours before first class
            if (isset($first_class_decimal) && ($first_class_decimal - $wake24) > 2.0) {
                $advice_wake = "You wake very early — ensure you get enough sleep to maintain alertness.";
            } else {
                $advice_wake = "Wake time is appropriate for your first class — maintain consistency.";
            }
        } else {
            $priorities['wake'] = 0;
            $advice_wake = "Wake time is within a typical range — maintain consistency.";
        }

        // Interaction-based combined advice
        $interactionAdvice = [];
        // 1. Wake time vs. first class
        if (isset($first_class_decimal)) {
            $gap = $first_class_decimal - $wake24; // hours between wake and first class
            if ($gap < 0) {
                $interactionAdvice[] = "Your first scheduled activity is at or before your typical wake time — consider waking earlier or adjusting your schedule so you have at least 15–30 minutes to prepare.";
            }
        }
        // 2. Commute + prep vs. gap
        $prep_time = 20; // minutes
        $gap_minutes = isset($first_class_decimal) ? ($first_class_decimal - $wake24) * 60 : null;
        if ($gap_minutes !== null && ($travel_avg + $prep_time) > $gap_minutes) {
            $interactionAdvice[] = "Your prep and travel time may not fit before your first class — risk of lateness. Try waking earlier or reducing prep time.";
        }
        // 3. Sleep consistency
        if ($sleep >= 7.0 && $sleep <= 8.5 && $wake24 >= 5.5 && $wake24 <= 7.5) {
            $interactionAdvice[] = "Your sleep and wake routine is optimal for punctuality. Keep it up!";
        }
        // 4. Lateness history
        if ($lateCount === 0) {
            $interactionAdvice[] = "You haven't been late recently. Try tracking how you feel each day to optimize further.";
        }
        // 5. Commute variability
        if (isset($travel_min_total) && isset($travel_max_total) && ($travel_max_total - $travel_min_total) > 20) {
            $interactionAdvice[] = "Your commute time varies a lot — plan for the worst-case to avoid surprises.";
        }
        // 6. Late first class, low sleep
        if (isset($first_class_decimal) && $first_class_decimal >= 11.0 && $sleep < 7.0) {
            $interactionAdvice[] = "Your first class is late in the day. Use the later start to catch up on sleep and improve alertness.";
        }
        // 7. Overlapping risks
        if ($sleep < 7.0 && $travel_avg > 45) {
            $interactionAdvice[] = "Short sleep and long commute together increase lateness risk — focus on improving both for best results.";
        }
        // 8. Custom buffer
        if ($lateCount > 0) {
            $buffer = min(15 + $lateCount * 2, 30);
            $interactionAdvice[] = "Try leaving about $buffer minutes earlier than usual based on your recent lateness.";
        }

        // Existing logic preserved:
        if ($sleep < 6.0 && $wake24 > 7.0 && $travel_avg > 30) {
            $interactionAdvice[] = "Your sleep, wake time, and commute combine to compress mornings. Move bedtime earlier by 30–60 minutes and leave 10–20 minutes earlier to create a reliable buffer.";
        }
        if ($lateCount >= 3 && ($wake24 > 7.0 || $travel_avg > 45)) {
            $interactionAdvice[] = "Because of repeated lateness, add a 15–30 minute buffer and practice your morning routine on a free day to find delays.";
        }

        // Advice that uses first-class context (prioritize based on proximity of first class to wake time)
        if (isset($first_class_decimal)) {
            $gap = $first_class_decimal - $wake24; // hours between wake and first class
            // If first class is before or very close after wake time
            if ($gap <= 0) {
                $interactionAdvice[] = "Your first scheduled activity is at or before your typical wake time — consider waking earlier or adjusting your schedule so you have at least 15–30 minutes to prepare.";
            } elseif ($gap < 0.25) { // less than 15 minutes
                $interactionAdvice[] = "Your first class is very soon after waking (less than 15 minutes). Try waking 15–30 minutes earlier or prepare more tasks the night before.";
            } elseif ($gap < 0.5) { // less than 30 minutes
                $interactionAdvice[] = "Your first class is 15–30 minutes after waking — adding a 10–20 minute buffer will reduce rush-related delays.";
            }
            // If first class is late in the day, deprioritize commute advice and focus on sleep consistency
            if ($first_class_decimal >= 11.0 && $sleep < 7.0) {
                $interactionAdvice[] = "Because your first class is later, prioritize regular bedtime to avoid accumulated sleep debt affecting punctuality on other days.";
            }
        }


        // Collect all candidate advices with priority scores
        $candidates = [
            ['k' => 'sleep', 'prio' => $priorities['sleep'], 'text' => $advice_sleep],
            ['k' => 'history', 'prio' => $priorities['history'], 'text' => $advice_history],
            ['k' => 'travel', 'prio' => $priorities['travel'], 'text' => $advice_travel],
            ['k' => 'wake', 'prio' => $priorities['wake'], 'text' => $advice_wake],
        ];

        // Sort candidates by priority desc
        usort($candidates, function($a, $b){ return $b['prio'] <=> $a['prio']; });

        // Append top 3 actionable items
        $count = 0;
        foreach ($candidates as $c) {
            if ($c['prio'] > 0 && $count < 3) {
                $adviceList[] = $c['text'];
                $count++;
            }
        }

        // Add interactions
        foreach ($interactionAdvice as $ia) {
            if ($count < 5) { $adviceList[] = $ia; $count++; }
        }

        // If no strong recommendations, show a maintenance tip
        if ($count === 0) {
            $adviceList[] = "Routine looks stable — maintain consistent bed/wake times and a 10–15 minute departure buffer.";
        }

        //  closing line tailored by risk
        if ($prediction === 'High') {
            $adviceList[] = '<em>Start with the highest-priority change above this week and re-check your results.</em>';
        } elseif ($prediction === 'Medium') {
            $adviceList[] = '<em>Try 1–2 changes this week and observe whether your on-time rate improves.</em>';
        } else {
            $adviceList[] = '<em>Keep tracking your routine and stay consistent.</em>';
        }

        $advice = implode("<br>", $adviceList);

        // --- Groq AI integration ---
        require_once 'groq_ai.php';
        $groqPrompt = sprintf(
            "Student routine: Sleep: %.1f hrs, Commute: %dm, Wake: %s, First class: %s, Recent lateness: %d. Give punctuality advice.",
            $sleep,
            $travel_avg,
            sprintf('%02d:%02d %s', $wake_hour_12, $wake_mins, $wake_ampm),
            sprintf('%02d:%02d %s', $first_class_hour_12, $first_class_mins, $first_class_ampm),
            $lateCount
        );
        $groqAdvice = get_groq_advice($groqApiKey, $groqPrompt);
        // results in session and redirect
        $_SESSION['prediction'] = $prediction;
        $_SESSION['adviceList'] = $adviceList;
        $_SESSION['inputValues'] = $inputValues;
        $_SESSION['travel_min_total'] = $travel_min_total;
        $_SESSION['travel_max_total'] = $travel_max_total;
        $_SESSION['travel_avg'] = $travel_avg;
        $_SESSION['groqAdvice'] = $groqAdvice;
        $_SESSION['wake_hour_12'] = $wake_hour_12;
        $_SESSION['wake_mins'] = $wake_mins;
        $_SESSION['wake_ampm'] = $wake_ampm;
        $_SESSION['first_class_hour_12'] = $first_class_hour_12;
        $_SESSION['first_class_mins'] = $first_class_mins;
        $_SESSION['first_class_ampm'] = $first_class_ampm;
        header("Location: predict.php");
        exit;
    }
}
// On GET, load and clear session results
if (isset($_SESSION['prediction'])) {
    $prediction = $_SESSION['prediction'];
    $adviceList = $_SESSION['adviceList'];
    $inputValues = $_SESSION['inputValues'];
    $travel_min_total = $_SESSION['travel_min_total'];
    $travel_max_total = $_SESSION['travel_max_total'];
    $travel_avg = $_SESSION['travel_avg'];
    $groqAdvice = $_SESSION['groqAdvice'];
    $wake_hour_12 = $_SESSION['wake_hour_12'];
    $wake_mins = $_SESSION['wake_mins'];
    $wake_ampm = $_SESSION['wake_ampm'];
    $first_class_hour_12 = $_SESSION['first_class_hour_12'];
    $first_class_mins = $_SESSION['first_class_mins'];
    $first_class_ampm = $_SESSION['first_class_ampm'];
    unset($_SESSION['prediction'], $_SESSION['adviceList'], $_SESSION['inputValues'], $_SESSION['travel_min_total'], $_SESSION['travel_max_total'], $_SESSION['travel_avg'], $_SESSION['groqAdvice'], $_SESSION['wake_hour_12'], $_SESSION['wake_mins'], $_SESSION['wake_ampm'], $_SESSION['first_class_hour_12'], $_SESSION['first_class_mins'], $_SESSION['first_class_ampm']);
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lateness Predictor</title>
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
      <a href="predict.php" class="active">Lateness Risk Predictor</a>
      <a href="about.php">About Us</a>
    </div>
  </div>
</nav>

<div class="container">
<h1>Check Your Lateness Risk</h1>
<p class="subtitle">Enter details about your typical sleep, commute, and schedule to receive an estimated lateness risk level and tailored recommendations.</p>

<?php if (!empty($errorMessage)): ?>
    <div class="error-box">
        <?php echo htmlspecialchars($errorMessage); ?>
    </div>
<?php endif; ?>

<!-- FORM -->
<h3>Routine Details</h3>
<form method="POST" class="prediction-form">
    <div class="form-group">
        <label for="sleep">Typical hours of sleep per night</label>
        <input type="number" id="sleep" name="sleep" step="0.5" min="0" max="24" placeholder="e.g., 7.5" required value="<?php echo isset($_POST['sleep']) ? htmlspecialchars($_POST['sleep']) : ''; ?>" aria-label="Typical hours of sleep per night">
        <span class="form-hint">Approximate average hours of sleep (for example, 7.5).</span>
    </div>

    <div class="form-group">
        <label>How long does your travel usually take?</label>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:12px;color:#666;">Min:</span>
                <input type="number" id="travel_min_hours" name="travel_min_hours" min="0" max="10" placeholder="0" required style="width:60px;" value="<?php echo isset($_POST['travel_min_hours']) ? htmlspecialchars($_POST['travel_min_hours']) : ''; ?>" aria-label="Minimum travel hours">
                <span style="font-size:12px;">h</span>
                <input type="number" id="travel_min_mins" name="travel_min_mins" min="0" max="59" placeholder="0" required style="width:60px;" value="<?php echo isset($_POST['travel_min_mins']) ? htmlspecialchars($_POST['travel_min_mins']) : ''; ?>" aria-label="Minimum travel minutes">
                <span style="font-size:12px;">m</span>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:12px;color:#666;">Max:</span>
                <input type="number" id="travel_max_hours" name="travel_max_hours" min="0" max="10" placeholder="0" required style="width:60px;" value="<?php echo isset($_POST['travel_max_hours']) ? htmlspecialchars($_POST['travel_max_hours']) : ''; ?>" aria-label="Maximum travel hours">
                <span style="font-size:12px;">h</span>
                <input type="number" id="travel_max_mins" name="travel_max_mins" min="0" max="59" placeholder="0" required style="width:60px;" value="<?php echo isset($_POST['travel_max_mins']) ? htmlspecialchars($_POST['travel_max_mins']) : ''; ?>" aria-label="Maximum travel minutes">
                <span style="font-size:12px;">m</span>
            </div>
        </div>
        <span class="form-hint">For example: minimum 30 minutes, maximum 1 hour 15 minutes.</span>
    </div>

    <div class="form-group">
        <label for="wake_hour">What time do you usually wake up?</label>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="number" id="wake_hour" name="wake_hour" min="1" max="12" placeholder="Hour" required style="width:70px;" value="<?php echo isset($_POST['wake_hour']) ? htmlspecialchars($_POST['wake_hour']) : ''; ?>" aria-label="Wake hour">
            <span style="font-size:18px;">:</span>
            <input type="number" id="wake_mins" name="wake_mins" min="0" max="59" placeholder="00" required style="width:70px;" value="<?php echo isset($_POST['wake_mins']) ? htmlspecialchars($_POST['wake_mins']) : ''; ?>" aria-label="Wake minutes">
            <select id="wake_ampm" name="wake_ampm" required style="width:90px;" aria-label="Wake AM or PM">
                <option value="AM" <?php if(isset($_POST['wake_ampm']) && $_POST['wake_ampm']==='AM') echo 'selected'; ?>>AM</option>
                <option value="PM" <?php if(isset($_POST['wake_ampm']) && $_POST['wake_ampm']==='PM') echo 'selected'; ?>>PM</option>
            </select>
        </div>
        <span class="form-hint">Hour : Minutes + AM/PM (for example, 7:30 AM).</span>
    </div>

    <div class="form-group">
        <label for="first_class_hour">First scheduled activity (for example, first class)</label>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="number" id="first_class_hour" name="first_class_hour" min="1" max="12" placeholder="Hour" required style="width:70px;" value="<?php echo isset($_POST['first_class_hour']) ? htmlspecialchars($_POST['first_class_hour']) : ''; ?>" aria-label="First class hour">
            <span style="font-size:18px;">:</span>
            <input type="number" id="first_class_mins" name="first_class_mins" min="0" max="59" placeholder="00" required style="width:70px;" value="<?php echo isset($_POST['first_class_mins']) ? htmlspecialchars($_POST['first_class_mins']) : ''; ?>" aria-label="First class minutes">
            <select id="first_class_ampm" name="first_class_ampm" required style="width:90px;" aria-label="First class AM or PM">
                <option value="AM" <?php if(isset($_POST['first_class_ampm']) && $_POST['first_class_ampm']==='AM') echo 'selected'; ?>>AM</option>
                <option value="PM" <?php if(isset($_POST['first_class_ampm']) && $_POST['first_class_ampm']==='PM') echo 'selected'; ?>>PM</option>
            </select>
        </div>
        <span class="form-hint">Time of your first class or commitment (used to relate your routine to your schedule).</span>
    </div>

    <div class="form-group">
        <label for="lateCount">How many times have you been late recently?</label>
        <input type="number" id="lateCount" name="lateCount" min="0" max="100" placeholder="e.g., 3" required value="<?php echo isset($_POST['lateCount']) ? htmlspecialchars($_POST['lateCount']) : ''; ?>" aria-label="Recent lateness count">
        <span class="form-hint">Estimated number of times in the past month.</span>
    </div>

    <button type="submit" aria-label="Submit lateness prediction form">Get Your Prediction</button>
    <p class="small">This demonstration does not store your inputs. Results are approximate and intended for personal reflection only.</p>
</form>

<!-- RESULT  -->
<?php if ($prediction !== null): ?>
    <h3>Prediction Result</h3>
    <div id="result" class="result-box">
        <div class="risk-indicator risk-<?php echo strtolower($prediction); ?>">
            <?php 
                $emoji = ['High' => '⚠️', 'Medium' => '⏱️', 'Low' => '✅'];
                echo $emoji[$prediction] ?? '•';
            ?>
            <span class="risk-label"><?php echo $prediction; ?> Risk</span>
        </div>
        
        <div class="advice-text">
            <p class="summary-title" style="margin-bottom: 8px;">Based on your inputs, consider the following:</p>
            <?php if (!empty($adviceList)): ?>
                <ul class="advice-list">
                    <?php foreach ($adviceList as $item): ?>
                        <li><?php echo $item; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No specific recommendations were generated. Your routine appears relatively stable.</p>
            <?php endif; ?>
        </div>
        <div class="advice-text" style="margin-top:18px; border-top:1px solid #e5e7eb; padding-top:12px;">
            <p class="summary-title" style="margin-bottom: 8px;">AI-powered advice (Groq):</p>
            <em style="font-size:13px; color:#6b7280;">This advice is generated by an AI model and may be more creative or nuanced than the rule-based suggestions above.</em><br>
            <?php
$aiAdvice = $groqAdvice;
if (preg_match_all('/^\d+\.\s*(.*)$/m', $aiAdvice, $matches)) {
    echo '<ol class="advice-list">';
    foreach ($matches[1] as $point) {
        $point = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $point);
        $point = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $point);
        echo '<li>' . $point . '</li>';
    }
    echo '</ol>';
} elseif (preg_match_all('/(<strong>.*?<\/strong>(?:.*?)(?=<strong>|$))/is', $aiAdvice, $bullets)) {
    echo '<ul class="advice-list">';
    foreach ($bullets[1] as $item) {
        echo '<li>' . trim($item) . '</li>';
    }
    echo '</ul>';
} else {
    //  show the full Groq advice
    echo '<div style="white-space:pre-line;word-break:break-word;">' . htmlspecialchars($aiAdvice) . '</div>';
}
?>

        </div>
    </div>
    <?php endif; ?>

<?php if ($prediction !== null): ?>
        <div class="input-summary">
            <p class="summary-title">Summary of Your Inputs</p>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-label">Sleep Duration</span>
                    <span class="summary-value"><?php echo $inputValues[0]; ?> hrs</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Commute Time</span>
                    <span class="summary-value"><?php $h_min = intdiv($travel_min_total, 60); $m_min = $travel_min_total % 60; $h_max = intdiv($travel_max_total, 60); $m_max = $travel_max_total % 60; echo htmlspecialchars(($h_min > 0 ? $h_min . 'h ' : '') . $m_min . 'm' . ' – ' . ($h_max > 0 ? $h_max . 'h ' : '') . $m_max . 'm (avg ' . round($travel_avg) . 'm)'); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Wake Time</span>
                    <span class="summary-value"><?php echo isset($wake_hour_12) && isset($wake_mins) && isset($wake_ampm) ? htmlspecialchars(sprintf('%02d:%02d %s', $wake_hour_12, $wake_mins, $wake_ampm)) : ''; ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">First Scheduled Activity</span>
                    <span class="summary-value"><?php echo isset($first_class_hour_12) && isset($first_class_mins) && isset($first_class_ampm) ? htmlspecialchars(sprintf('%02d:%02d %s', $first_class_hour_12, $first_class_mins, $first_class_ampm)) : ''; ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Recent Lateness</span>
                    <span class="summary-value"><?php echo (int)$inputValues[3]; ?> times</span>
                </div>
            </div>
        </div>
<?php endif; ?>
    <a href="index.php" class="back-link">← Back to Home</a>
</div>

<footer class="site-footer">
  <p> 2025 SLRP – Student Lateness Risk Predictor. Developed for ITEP 308, 3WMAD2.</p>
</footer>

<?php if ($prediction !== null): ?>
<script>
  window.addEventListener('load', function () {
    var resultEl = document.getElementById('result');
    if (resultEl) {
      resultEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
</script>
<?php endif; ?>

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
