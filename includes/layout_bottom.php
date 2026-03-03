        <footer class="app-footer">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <span>Designed & Developed by</span>
                <strong style="background: linear-gradient(135deg, #4361ee, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Ocean Infotech</strong>
            </div>
        </footer>
    </main>
</div>

<!-- Animated Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3 app-toast-wrap" id="appToastWrap" style="z-index: 9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

<!-- Enhanced Animated Toast Function -->
<script>
function showAppToast(type, message) {
    const wrap = document.getElementById("appToastWrap");
    if (!wrap || !message) return;
    
    // Color configurations for each type
    const configs = {
        success: {
            gradient: 'linear-gradient(135deg, #06d6a0 0%, #10b981 100%)',
            bg: 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)',
            border: '#10b981',
            icon: 'fa-circle-check',
            label: 'Success',
            glow: '0 0 20px rgba(6, 214, 160, 0.4)'
        },
        error: {
            gradient: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            bg: 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)',
            border: '#dc2626',
            icon: 'fa-circle-xmark',
            label: 'Error',
            glow: '0 0 20px rgba(239, 68, 68, 0.4)'
        },
        warning: {
            gradient: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            bg: 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)',
            border: '#d97706',
            icon: 'fa-triangle-exclamation',
            label: 'Warning',
            glow: '0 0 20px rgba(245, 158, 11, 0.4)'
        },
        info: {
            gradient: 'linear-gradient(135deg, #4361ee 0%, #7c3aed 100%)',
            bg: 'linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%)',
            border: '#4361ee',
            icon: 'fa-circle-info',
            label: 'Info',
            glow: '0 0 20px rgba(67, 97, 238, 0.4)'
        }
    };
    
    const config = configs[type] || configs.info;
    
    const el = document.createElement("div");
    el.className = "toast align-items-center border-0 app-toast";
    el.setAttribute("role", "alert");
    el.setAttribute("aria-live", "assertive");
    el.setAttribute("aria-atomic", "true");
    el.style.setProperty("--toast-delay", "4000ms");
    el.style.background = config.bg;
    el.style.border = `2px solid ${config.border}`;
    el.style.borderRadius = "16px";
    el.style.boxShadow = `0 12px 40px rgba(0,0,0,0.15), ${config.glow}`;
    el.style.overflow = "hidden";
    el.style.animation = "toastSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1)";
    
    // Inner HTML with icon
    el.innerHTML = `
        <div class="toast-progress" style="background: ${config.gradient}; height: 4px;"></div>
        <div class="d-flex p-3">
            <div class="toast-icon-wrap" style="
                width: 44px; 
                height: 44px; 
                border-radius: 12px; 
                background: ${config.gradient};
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 12px;
                box-shadow: ${config.glow};
                flex-shrink: 0;
            ">
                <i class="fa-solid ${config.icon} text-white fs-5"></i>
            </div>
            <div class="flex-grow-1" style="min-width: 0;">
                <div class="toast-title" style="
                    font-weight: 700; 
                    color: #1f2937; 
                    font-size: 0.95rem;
                    margin-bottom: 2px;
                ">
                    ${config.label}
                </div>
                <div class="toast-msg" style="
                    font-weight: 500; 
                    color: #4b5563; 
                    font-size: 0.875rem;
                    word-wrap: break-word;
                ">
                    ${message}
                </div>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" style="opacity: 0.6;"></button>
        </div>
    `;
    
    wrap.appendChild(el);
    
    const toast = new bootstrap.Toast(el, { delay: 4000 });
    toast.show();
    
    // Add slide out animation when hidden
    el.addEventListener("hidden.bs.toast", function () {
        el.style.animation = "toastSlideOut 0.3s ease-in forwards";
        setTimeout(() => el.remove(), 300);
    });
}

// Add toast slide animations
const style = document.createElement('style');
style.textContent = `
    @keyframes toastSlideIn {
        0% {
            opacity: 0;
            transform: translateX(100px) scale(0.9);
        }
        100% {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }
    @keyframes toastSlideOut {
        0% {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
        100% {
            opacity: 0;
            transform: translateX(100px) scale(0.9);
        }
    }
    @keyframes toastProgress {
        0% { transform: scaleX(1); transform-origin: left; }
        100% { transform: scaleX(0); transform-origin: left; }
    }
    
    .toast-progress {
        animation: toastProgress var(--toast-delay, 4000ms) linear forwards;
    }
`;
document.head.appendChild(style);
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const body = document.body;
    const toggleBtn = document.getElementById("sidebarToggleBtn");
    const closeBtn = document.getElementById("sidebarCloseBtn");
    const backdrop = document.getElementById("sidebarBackdrop");

    function openSidebar() { body.classList.add("sidebar-open"); }
    function closeSidebar() { body.classList.remove("sidebar-open"); }

    if (toggleBtn) toggleBtn.addEventListener("click", openSidebar);
    if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
    if (backdrop) backdrop.addEventListener("click", closeSidebar);
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeSidebar();
    });
});
</script>

<?php
$flashToasts = consumeFlashToasts();
if ($flashToasts) {
    foreach ($flashToasts as $t) {
        $type = clean_input($t["type"] ?? "success");
        $msg = $t["message"] ?? "";
        echo "<script>document.addEventListener('DOMContentLoaded',function(){showAppToast(" . json_encode($type) . "," . json_encode($msg) . ");});</script>";
    }
}
?>
</body>
</html>
