<div id="pwa-install-banner">
    <div class="pwa-banner-header">
        <img class="pwa-banner-logo" src="/icons/icon-192.png" alt="ងាយជួល">
        <div class="pwa-banner-text">
            <h4 class="pwa-banner-title">ងាយជួល</h4>
            <p class="pwa-banner-desc">Add to Home Screen · Works offline</p>
        </div>
    </div>
    <div class="pwa-banner-actions">
        <button id="pwa-dismiss-btn" class="pwa-btn-dismiss">Not now</button>
        <button id="pwa-install-btn" class="pwa-btn-install">ដំឡើង</button>
    </div>
</div>

<style>
    #pwa-install-banner {
        display: none;
        position: fixed;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        width: calc(100% - 2rem);
        max-width: 420px;
        background: rgba(255, 255, 255, 0.85);
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(5, 150, 105, 0.25);
        border-radius: 1rem;
        padding: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.15), 0 8px 10px -6px rgba(5, 150, 105, 0.15);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        box-sizing: border-box;
    }
    #pwa-install-banner * {
        box-sizing: border-box;
    }
    .pwa-banner-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .pwa-banner-logo {
        width: 48px;
        height: 48px;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        object-fit: cover;
    }
    .pwa-banner-text {
        flex: 1;
        min-width: 0;
    }
    .pwa-banner-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
    }
    .pwa-banner-desc {
        margin: 0.15rem 0 0 0;
        font-size: 0.75rem;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pwa-banner-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }
    .pwa-btn-dismiss {
        flex: 1;
        padding: 0.5rem 1rem;
        background: transparent;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .pwa-btn-dismiss:hover {
        background: rgba(0, 0, 0, 0.05);
        border-color: #94a3b8;
    }
    .pwa-btn-install {
        flex: 1;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #059669, #047857);
        border: none;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: white;
        cursor: pointer;
        transition: opacity 0.15s, transform 0.1s;
        box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.2);
    }
    .pwa-btn-install:hover {
        opacity: 0.95;
        transform: translateY(-0.5px);
    }
    .pwa-btn-install:active {
        transform: translateY(0);
    }
</style>

<script>
(function() {
    // Check if we should ignore showing it
    if (window.matchMedia('(display-mode: standalone)').matches || localStorage.getItem('pwa-banner-dismissed')) {
        return;
    }

    let deferredPrompt = null;
    const banner = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');
    const dismissBtn = document.getElementById('pwa-dismiss-btn');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        // Show the banner with a 2500ms delay
        setTimeout(() => {
            if (banner && !localStorage.getItem('pwa-banner-dismissed')) {
                banner.style.display = 'block';
            }
        }, 2500);
    });

    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            // Show the install prompt
            deferredPrompt.prompt();
            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                localStorage.setItem('pwa-banner-dismissed', '1');
                if (banner) banner.style.display = 'none';
            }
            deferredPrompt = null;
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            localStorage.setItem('pwa-banner-dismissed', '1');
            if (banner) banner.style.display = 'none';
        });
    }

    window.addEventListener('appinstalled', (event) => {
        localStorage.setItem('pwa-banner-dismissed', '1');
        if (banner) banner.style.display = 'none';
        deferredPrompt = null;
    });
})();
</script>
