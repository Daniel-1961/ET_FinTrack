<?php
/**
 * FinTrack ET - Welcome and Login Portal
 * High aesthetics welcome gateway offering user account logins, registration, and bilingual switches.
 */
require_once 'config.php';
require_once 'auth.php';

// Redirect if already authenticated
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$errorMsgEn = "";
$errorMsgAm = "";
$successMsgEn = "";
$successMsgAm = "";

$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Form Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_btn'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $errorMsgEn = "Please fill in all fields.";
            $errorMsgAm = "እባክዎ ሁሉንም ክፍት ቦታዎች ይሙሉ::";
        } else {
            // Find user using secure prepared statement
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['business_name'] = $user['business_name'];
                
                header("Location: dashboard.php");
                exit;
            } else {
                $errorMsgEn = "Invalid username or password.";
                $errorMsgAm = "የተሳሳተ የተጠቃሚ ስም ወይም የይለፍ ቃል::";
            }
        }
    } elseif (isset($_POST['register_btn'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $business_name = trim($_POST['business_name']);

        if (empty($username) || empty($password) || empty($business_name)) {
            $errorMsgEn = "Please fill in all fields.";
            $errorMsgAm = "እባክዎ ሁሉንም ክፍት ቦታዎች ይሙሉ::";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errorMsgEn = "Username already taken.";
                $errorMsgAm = "ይህ የተጠቃሚ ስም ቀደም ብሎ ተይዟል::";
            } else {
                // Register user with encrypted BCrypt password
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, business_name) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$username, $hashedPass, $business_name])) {
                    $successMsgEn = "Registration successful! You can now log in.";
                    $successMsgAm = "ምዝገባው በተሳካ ሁኔታ ተጠናቋል! አሁን መግባት ይችላሉ::";
                    $action = 'login'; // redirect view to login
                } else {
                    $errorMsgEn = "Registration failed. Please try again.";
                    $errorMsgAm = "ምዝገባው አልተሳካም። እባክዎ እንደገና ይሞክሩ::";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack ET (ፋይናንስ ትራክ) - Welcome Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-card {
            position: relative;
        }
        .auth-lang-float {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 110px;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            
            <!-- Lang Switcher inside Auth card -->
            <div class="lang-toggle auth-lang-float" id="auth-lang-switcher">
                <div class="lang-btn active" id="btn-auth-en">EN</div>
                <div class="lang-btn" id="btn-auth-am">አማርኛ</div>
            </div>

            <div class="auth-logo">
                <div class="icon">F</div>
                <h2 id="auth-title-main">FinTrack ET</h2>
                <p id="auth-subtitle-main">Accessible digital financial manager</p>
            </div>

            <!-- Error and Success Alerts -->
            <?php if (!empty($errorMsgEn)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.9rem;">
                    <div class="lang-text-en" style="color: var(--danger); font-weight: 500;"><?= htmlspecialchars($errorMsgEn) ?></div>
                    <div class="lang-text-am" style="color: var(--danger); font-weight: 500; display: none;"><?= htmlspecialchars($errorMsgAm) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMsgEn)): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.9rem;">
                    <div class="lang-text-en" style="color: var(--success); font-weight: 500;"><?= htmlspecialchars($successMsgEn) ?></div>
                    <div class="lang-text-am" style="color: var(--success); font-weight: 500; display: none;"><?= htmlspecialchars($successMsgAm) ?></div>
                </div>
            <?php endif; ?>

            <!-- LOGIN VIEW -->
            <?php if ($action === 'login'): ?>
                <form action="index.php?action=login" method="POST" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label" id="lbl-username">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. almaz">
                    </div>

                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                            <label class="form-label" id="lbl-password" style="margin-bottom:0;">Password</label>
                        </div>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" name="login_btn" class="btn btn-primary" style="width: 100%;" id="btn-login">Log In to Business</button>
                    </div>

                    <div style="text-align: center; margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary);">
                        <span id="txt-no-account">Don't have a retail profile?</span> 
                        <a href="index.php?action=register" style="font-weight: 600;" id="lnk-register">Register Shop</a>
                    </div>
                </form>
            <?php else: ?>
                <!-- REGISTER VIEW -->
                <form action="index.php?action=register" method="POST" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label" id="lbl-reg-business">Business / Shop Name</label>
                        <input type="text" name="business_name" class="form-control" required placeholder="e.g. Almaz Grocery, Merkato Stall #12">
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lbl-reg-username">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. almaz">
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="lbl-reg-password">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Create password">
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" name="register_btn" class="btn btn-accent" style="width: 100%;" id="btn-register-submit">Register New Profile</button>
                    </div>

                    <div style="text-align: center; margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary);">
                        <span id="txt-have-account">Already registered?</span> 
                        <a href="index.php?action=login" style="font-weight: 600;" id="lnk-login">Log In</a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Interactive quick info about test credentials -->
            <div style="margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 20px; font-size: 0.8rem; color: var(--text-secondary); text-align: center;">
                <i class="fas fa-circle-info" style="color: var(--secondary); margin-right: 5px;"></i>
                <span id="txt-sample-tip">Use sample account: <strong>almaz</strong> & password: <strong>admin123</strong></span>
            </div>

        </div>
    </div>

    <script>
        const translations = {
            en: {
                title: "FinTrack ET",
                subtitle: "Accessible digital financial manager",
                lbl_username: "Username",
                lbl_password: "Password",
                btn_login: "Log In to Business",
                txt_no_account: "Don't have a retail profile?",
                lnk_register: "Register Shop",
                lbl_reg_business: "Business / Shop Name",
                lbl_reg_username: "Username",
                lbl_reg_password: "Password",
                btn_register_submit: "Register New Profile",
                txt_have_account: "Already registered?",
                lnk_login: "Log In",
                txt_sample_tip: "Use sample account: <strong>almaz</strong> & password: <strong>admin123</strong>"
            },
            am: {
                title: "ፋይናንስ ትራክ",
                subtitle: "ቀላል እና ፈጣን የፋይናንስ መቆጣጠሪያ",
                lbl_username: "የተጠቃሚ ስም",
                lbl_password: "የይለፍ ቃል",
                btn_login: "ወደ መለያ ይግቡ",
                txt_no_account: "መለያ የለዎትም?",
                lnk_register: "አዲስ ሱቅ ይመዝግቡ",
                lbl_reg_business: "የሱቅ / የቢዝነስ ስም",
                lbl_reg_username: "የተጠቃሚ ስም",
                lbl_reg_password: "የይለፍ ቃል",
                btn_register_submit: "አዲስ መለያ ፍጠር",
                txt_have_account: "ቀደም ሲል ተመዝግበዋል?",
                lnk_login: "ይግቡ",
                txt_sample_tip: "መሞከሪያ ሂሳብ፡ የተጠቃሚ ስም <strong>almaz</strong> ፣ የይለፍ ቃል <strong>admin123</strong>"
            }
        };

        function switchLanguage(lang) {
            const dictionary = translations[lang];
            
            // Text values updates
            document.getElementById('auth-title-main').textContent = dictionary.title;
            document.getElementById('auth-subtitle-main').textContent = dictionary.subtitle;
            
            const tipSpan = document.getElementById('txt-sample-tip');
            if (tipSpan) tipSpan.innerHTML = dictionary.txt_sample_tip;

            // Form specifics
            if (document.getElementById('lbl-username')) {
                document.getElementById('lbl-username').textContent = dictionary.lbl_username;
                document.getElementById('lbl-password').textContent = dictionary.lbl_password;
                document.getElementById('btn-login').textContent = dictionary.btn_login;
                document.getElementById('txt-no-account').textContent = dictionary.txt_no_account;
                document.getElementById('lnk-register').textContent = dictionary.lnk_register;
            }

            if (document.getElementById('lbl-reg-business')) {
                document.getElementById('lbl-reg-business').textContent = dictionary.lbl_reg_business;
                document.getElementById('lbl-reg-username').textContent = dictionary.lbl_reg_username;
                document.getElementById('lbl-reg-password').textContent = dictionary.lbl_reg_password;
                document.getElementById('btn-register-submit').textContent = dictionary.btn_register_submit;
                document.getElementById('txt-have-account').textContent = dictionary.txt_have_account;
                document.getElementById('lnk-login').textContent = dictionary.lnk_login;
            }

            // Alert messages visibility toggles
            document.querySelectorAll('.lang-text-en').forEach(el => el.style.display = (lang === 'en') ? 'block' : 'none');
            document.querySelectorAll('.lang-text-am').forEach(el => el.style.display = (lang === 'am') ? 'block' : 'none');

            // Button toggle
            document.getElementById('btn-auth-en').classList.toggle('active', lang === 'en');
            document.getElementById('btn-auth-am').classList.toggle('active', lang === 'am');
        }

        document.getElementById('btn-auth-en').addEventListener('click', () => switchLanguage('en'));
        document.getElementById('btn-auth-am').addEventListener('click', () => switchLanguage('am'));
    </script>
</body>
</html>
