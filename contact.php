<?php
ob_start();
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$uid = (int)$_SESSION['user_id'];
$user = getUserData($uid);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $subject  = sanitize($_POST['subject'] ?? '');
    $message  = sanitize($_POST['message'] ?? '');

    // If category is not "Other", force subject to the category name
    if ($category !== 'Other' && !empty($category)) {
        $subject = $category;
    }

    if (empty($name) || empty($email) || empty($category) || empty($subject) || empty($message)) {
        $error = "Please fill in all required fields.";
    } else {
        // Store in database (subject contains the issue)
        $insert = mysqli_query($conn,
            "INSERT INTO contact_messages (user_id, name, email, subject, message)
             VALUES ($uid, '$name', '$email', '$subject', '$message')"
        );

        // Send email (optional)
        $to      = "support@jynxbattle.online";
        $headers = "From: $email\r\nReply-To: $email\r\n";
        $body    = "New message from $name ($email)\n\nCategory: $category\nSubject: $subject\n\n$message";
        @mail($to, "HelpGo Support: $subject", $body, $headers);

        if ($insert) {
            $success = "Thank you! Your message has been sent. We'll get back to you shortly.";
            $_POST = []; // clear form
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Contact Support – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33;
            --emerald-light: #0E5548;
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8962E;
            --white: #ffffff;
            --gray-soft: #AEB8B2;
            --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.65);
            --glass-border: rgba(212, 175, 55, 0.2);
            --shadow-glass: 0 20px 60px rgba(0, 0, 0, 0.4);
            --radius-card: 24px;
            --radius-input: 16px;
            --radius-btn: 16px;
            --font: 'Poppins', sans-serif;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: radial-gradient(ellipse at 20% 0%, #0E5548 0%, #04261F 70%, #083C33 100%);
            color: var(--white);
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding: 20px 16px 60px;
            position: relative;
            overflow-x: hidden;
            animation: fadeInPage 1s ease;
        }
        @keyframes fadeInPage { from { opacity: 0; } to { opacity: 1; } }

        /* Floating glowing elements */
        .floating-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation: float 12s infinite alternate ease-in-out;
        }
        .floating-orb:nth-child(1) { width: 400px; height: 400px; background: var(--gold); top: -100px; right: -100px; }
        .floating-orb:nth-child(2) { width: 250px; height: 250px; background: var(--gold); bottom: -80px; left: -80px; animation-delay: -6s; }
        .floating-orb:nth-child(3) { width: 150px; height: 150px; background: var(--gold); top: 40%; left: 50%; animation-delay: -10s; }

        .gold-particles {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            background-image: radial-gradient(circle, rgba(212,175,55,0.15) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: particleMove 20s linear infinite;
        }
        @keyframes particleMove { 0% { transform: translateY(0); } 100% { transform: translateY(-30px); } }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(30px, -20px) scale(1.1); } }

        .container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 2;
        }

        /* Back button */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 30px;
            transition: var(--transition);
        }
        .back-link:hover { gap: 14px; }

        /* Hero section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .hero-text { flex: 1; }
        .hero-text h1 { font-size: 32px; font-weight: 800; line-height: 1.2; color: var(--white); }
        .hero-text h1 span { color: var(--gold); }
        .hero-text p { color: var(--gray-soft); font-size: 14px; margin-top: 8px; }
        .hero-icon {
            font-size: 70px;
            color: var(--gold);
            opacity: 0.9;
            animation: floatIcon 4s infinite ease-in-out;
        }
        @keyframes floatIcon { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

        /* Quick contact cards */
        .quick-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 28px;
        }
        .quick-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 16px;
            text-align: center;
            text-decoration: none;
            color: var(--white);
            transition: var(--transition);
            box-shadow: var(--shadow-glass);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .quick-card i { font-size: 24px; color: var(--gold); }
        .quick-card span { font-weight: 500; font-size: 14px; }
        .quick-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0 25px rgba(212,175,55,0.25);
            border-color: var(--gold);
        }

        /* Main form card */
        .form-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 28px 24px;
            box-shadow: var(--shadow-glass);
            margin-bottom: 24px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-soft);
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--radius-input);
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--white);
            font-family: var(--font);
            font-size: 15px;
            outline: none;
            transition: var(--transition);
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
            background: rgba(255,255,255,0.08);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 130px;
        }
        .form-group select option {
            background: var(--emerald);
            color: var(--white);
        }

        .file-upload-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .file-upload-box {
            flex: 1;
            padding: 14px 18px;
            border-radius: var(--radius-input);
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--gray-soft);
            font-size: 14px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-upload-box input[type="file"] {
            position: absolute;
            left: 0; top: 0;
            width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .char-counter {
            text-align: right;
            font-size: 12px;
            color: var(--gray-muted);
            margin-top: 4px;
        }

        /* Support status box */
        .status-box {
            background: rgba(46, 213, 115, 0.08);
            border: 1px solid rgba(46, 213, 115, 0.2);
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 14px;
            color: var(--gray-soft);
        }
        .status-box i { color: #2ED573; margin-right: 10px; }
        .status-item { display: flex; align-items: center; }

        /* Send button */
        .send-btn {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: var(--radius-btn);
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald);
            font-family: var(--font);
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(212,175,55,0.3);
            margin-bottom: 30px;
        }
        .send-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(212,175,55,0.5);
        }
        .send-btn:active { transform: scale(0.98); }

        /* Responsive */
        @media (max-width: 400px) {
            .hero-icon { font-size: 50px; }
            .hero-text h1 { font-size: 26px; }
            .quick-cards { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <!-- Floating elements -->
    <div class="floating-orb"></div>
    <div class="floating-orb"></div>
    <div class="floating-orb"></div>
    <div class="gold-particles"></div>

    <div class="container">
        <!-- Back button -->
        <a href="home.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <!-- Hero section -->
        <div class="hero">
            <div class="hero-text">
                <h1>Contact <span>Support</span></h1>
                <p>Need help? Tell us what's wrong and our support team will respond as quickly as possible.</p>
            </div>
            <div class="hero-icon">
                <i class="fas fa-headset"></i>
            </div>
        </div>

        <!-- Quick contact cards -->
        <div class="quick-cards">
            <a href="tel:+911234567890" class="quick-card">
                <i class="fas fa-phone-volume"></i>
                <span>Call Support</span>
            </a>
            <a href="https://wa.me/918714851906" target="_blank" class="quick-card">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
            <a href="mailto:support@helpto.com" class="quick-card">
                <i class="fas fa-envelope"></i>
                <span>Email</span>
            </a>
            <div class="quick-card" id="liveChatBtn">
                <i class="fas fa-comment-dots"></i>
                <span>Live Chat</span>
            </div>
        </div>

        <!-- Main form card -->
        <div class="form-card">
            <form id="supportForm">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="+91 98765 43210" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label>Issue Category *</label>
                    <select name="category" required>
                        <option value="">-- Select Issue --</option>
                        <option value="Order Not Found">Order Not Found</option>
                        <option value="Order Stuck">Order Stuck</option>
                        <option value="Withdrawal Issue">Withdrawal Issue</option>
                        <option value="Payment Issue">Payment Issue</option>
                        <option value="Account Issue">Account Issue</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Order ID (optional)</label>
                    <input type="text" name="order_id" placeholder="e.g. HLP202607169279">
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="Brief description of your issue">
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" id="messageBox" placeholder="Describe your issue in detail..." required maxlength="500"></textarea>
                    <div class="char-counter"><span id="charCount">0</span>/500</div>
                </div>
                <div class="form-group">
                    <label>Attach Image (optional)</label>
                    <div class="file-upload-wrapper">
                        <div class="file-upload-box" id="fileNameDisplay">Choose a file</div>
                        <input type="file" id="fileInput" accept="image/*">
                    </div>
                </div>
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i> Send Support Request
                </button>
            </form>
        </div>

        <!-- Support status box -->
        <div class="status-box">
            <div class="status-item"><i class="fas fa-check-circle"></i> Average response time: 5–15 minutes</div>
            <div class="status-item"><i class="fas fa-check-circle"></i> Available: 24/7</div>
            <div class="status-item"><i class="fas fa-check-circle"></i> Usually replies instantly on WhatsApp</div>
        </div>
    </div>

    <script>
        // Live chat simulation
        document.getElementById('liveChatBtn').addEventListener('click', function() {
            alert('Live chat would open here. (Simulated)');
        });

        // File name display
        document.getElementById('fileInput').addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose a file';
            document.getElementById('fileNameDisplay').textContent = fileName;
        });

        // Character counter
        const messageBox = document.getElementById('messageBox');
        const charCount = document.getElementById('charCount');
        messageBox.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Form submission (simulated)
        document.getElementById('supportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = this.name.value.trim();
            const phone = this.phone.value.trim();
            const category = this.category.value;
            const message = this.message.value.trim();
            if (!name || !phone || !category || !message) {
                alert('Please fill in all required fields (Name, Phone, Category, Message).');
                return;
            }
            // Simulate sending
            alert('Thank you, ' + name + '! Your support request has been submitted. We will contact you shortly.');
            this.reset();
            document.getElementById('fileNameDisplay').textContent = 'Choose a file';
            charCount.textContent = '0';
        });
    </script>
</body>
</html>