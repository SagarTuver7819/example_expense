<?php
require_once __DIR__ . "/includes/bootstrap.php";

if (isLoggedIn()) {
    redirect("dashboard.php");
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = clean_input($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT u.id, u.name, u.email, u.password, u.status, r.id AS role_id, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email
         LIMIT 1"
    );
    $stmt->execute(["email" => $email]);
    $user = $stmt->fetch();

    if ($user && $user["status"] === "Active" && password_verify($password, $user["password"])) {
        hydrateSessionFromUser($user);
        notifyRole($conn, "Admin", "User Logged In", $user["name"] . " logged in.");
        redirect("dashboard.php");
    }

    $error = "Invalid credentials or inactive account.";
}

$pageTitle = "Login";
require_once __DIR__ . "/includes/layout_top.php";

$brandLogo = "assets/img/ocean-mark.png";
if (file_exists(__DIR__ . "/assets/img/ocean-mark.png")) {
    $brandLogo = "assets/img/ocean-mark.png";
} elseif (file_exists(__DIR__ . "/assets/img/ocean-wordmark.png")) {
    $brandLogo = "assets/img/ocean-wordmark.png";
}
?>
<style>
/* Enhanced Login Page Styles */
.login-page-wrapper {
    position: relative;
    overflow: hidden;
}

/* Animated Background Orbs */
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.5;
    animation: floatOrb 15s ease-in-out infinite;
}

.orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #4361ee, #7c3aed);
    top: -15%;
    left: -10%;
    animation-delay: 0s;
}

.orb-2 {
    width: 350px;
    height: 350px;
    background: linear-gradient(135deg, #f72585, #ff6b6b);
    bottom: -10%;
    right: -5%;
    animation-delay: -5s;
}

.orb-3 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #06d6a0, #4895ef);
    top: 40%;
    right: 20%;
    animation-delay: -10s;
}

@keyframes floatOrb {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(30px, -30px) scale(1.05);
    }
    50% {
        transform: translate(-20px, 20px) scale(0.95);
    }
    75% {
        transform: translate(-30px, -20px) scale(1.02);
    }
}

/* Card Float Animation */
@keyframes cardFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.auth-card {
    animation: cardFloat 6s ease-in-out infinite;
}

/* Input Focus Animation */
.form-control {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.form-control:focus {
    transform: translateY(-2px);
}

/* Button Pulse Animation */
@keyframes btnPulse {
    0%, 100% {
        box-shadow: 0 8px 24px rgba(67, 97, 238, 0.35);
    }
    50% {
        box-shadow: 0 8px 32px rgba(67, 97, 238, 0.5), 0 0 0 8px rgba(67, 97, 238, 0.1);
    }
}

.btn-primary {
    animation: btnPulse 2s ease-in-out infinite;
}

/* Gradient Text Animation */
@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.gradient-text {
    background: linear-gradient(135deg, #4361ee, #7c3aed, #f72585, #4361ee);
    background-size: 300% 300%;
    animation: gradientShift 5s ease infinite;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Badge Bounce */
@keyframes badgeBounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

.login-badge {
    animation: badgeBounce 2s ease-in-out infinite;
}

.login-badge:nth-child(2) {
    animation-delay: 0.3s;
}

.login-badge:nth-child(3) {
    animation-delay: 0.6s;
}

/* Shake Animation for Error */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.shake {
    animation: shake 0.5s ease-in-out;
}

/* Icon Wiggle */
@keyframes iconWiggle {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

.form-label i {
    animation: iconWiggle 3s ease-in-out infinite;
}
</style>

<div class="auth-wrapper login-page-wrapper">
    <!-- Animated Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?php echo clean_input($brandLogo); ?>" alt="Ocean Infotech" class="auth-logo">
            <div class="auth-subtitle gradient-text">Expense Manager Pro</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger shake" style="
                background: linear-gradient(135deg, #fee2e2, #fecaca); 
                border: 1px solid #fecaca; 
                border-radius: 14px; 
                padding: 1rem;
                color: #dc2626; 
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            ">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem;"></i> 
                <?php echo clean_input($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="loginForm">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fa-solid fa-envelope"></i> Email Address
                </label>
                <input class="form-control" type="email" name="email" required placeholder="Enter your email" 
                       style="padding-left: 1rem;">
            </div>
            <div class="mb-4">
                <label class="form-label">
                    <i class="fa-solid fa-lock"></i> Password
                </label>
                <input class="form-control" type="password" name="password" required placeholder="Enter your password"
                       style="padding-left: 1rem;">
            </div>
            <button class="btn btn-primary w-100" type="submit" id="loginBtn">
                <i class="fa-solid fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid rgba(67, 97, 238, 0.1); text-align: center;">
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                <i class="fa-solid fa-info-circle" style="color: #4361ee; animation: iconWiggle 2s ease-in-out infinite;"></i> 
                Default Admin: <strong style="color: #1e3a5f;">admin@ocean.com</strong> / <strong style="color: #1e3a5f;">admin123</strong>
            </p>
        </div>
        
        <div style="margin-top: 1.25rem; display: flex; justify-content: center; gap: 0.6rem; flex-wrap: wrap;">
            <span class="login-badge" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; background: rgba(67, 97, 238, 0.12); border-radius: 25px; font-size: 0.75rem; font-weight: 600; color: #4361ee; border: 1px solid rgba(67, 97, 238, 0.2);">
                <i class="fa-solid fa-shield-halved"></i> Secure Login
            </span>
            <span class="login-badge" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; background: rgba(6, 214, 160, 0.12); border-radius: 25px; font-size: 0.75rem; font-weight: 600; color: #059669; border: 1px solid rgba(6, 214, 160, 0.2);">
                <i class="fa-solid fa-bolt"></i> Real-time
            </span>
            <span class="login-badge" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; background: rgba(124, 58, 237, 0.12); border-radius: 25px; font-size: 0.75rem; font-weight: 600; color: #7c3aed; border: 1px solid rgba(124, 58, 237, 0.2);">
                <i class="fa-solid fa-chart-pie"></i> ERP Ready
            </span>
        </div>
        
        <!-- Decorative animated line -->
        <div style="margin-top: 1.25rem; height: 3px; background: linear-gradient(90deg, transparent, #4361ee, #7c3aed, #f72585, transparent); border-radius: 2px; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent); animation: shimmer 2s infinite;"></div>
        </div>
    </div>
</div>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<div class="auth-footer">
    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
        <span>Designed & Developed by</span>
        <strong class="gradient-text" style="font-weight: 700;">Ocean Infotech</strong>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple effect on button click
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            const btn = e.currentTarget;
            const rect = btn.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                background: rgba(255, 255, 255, 0.4);
                border-radius: 50%;
                width: 100px;
                height: 100px;
                left: ${x - 50}px;
                top: ${y - 50}px;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;
            
            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';
            btn.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    }
    
    // Add ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Add floating label effect on inputs
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
});
</script>
</body>
</html>
