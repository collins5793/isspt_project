<?php
/**
 * Footer du dashboard
 * Version: 1.0.0
 */
?>
<style>
    
/* ============================================
   FOOTER STYLES
   ============================================ */
.admin-footer {
    margin-top: var(--space-6);
    padding: var(--space-4) 0;
    border-top: 1px solid var(--gray-200);
    color: var(--gray-600);
}

.footer-content {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-4);
}

.footer-left {
    flex: 1;
    min-width: 0;
}

.footer-right {
    display: flex;
    align-items: center;
    gap: var(--space-4);
}

.copyright {
    margin: 0;
    font-size: var(--font-size-sm);
}

.version {
    background: var(--gray-200);
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    margin-left: var(--space-2);
}

.footer-links {
    margin: var(--space-2) 0 0 0;
    display: flex;
    align-items: center;
    gap: var(--space-2);
    flex-wrap: wrap;
}

.footer-links a {
    color: var(--gray-600);
    text-decoration: none;
    font-size: var(--font-size-sm);
    transition: var(--transition-base);
}

.footer-links a:hover {
    color: var(--accent-red);
}

.separator {
    color: var(--gray-400);
    user-select: none;
}

.system-info {
    display: flex;
    align-items: center;
    gap: var(--space-4);
}

.server-time,
.server-status {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--font-size-sm);
}

</style>
<footer class="admin-footer">
    <div class="footer-content">
        <div class="footer-left">
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> ISSPT - Institut Supérieur des Sciences, des Technologies et de la Pédagogie.
                <span class="version">v1.0.0</span>
            </p>
            <p class="footer-links">
                <a href="<?php echo BASE_URL; ?>privacy.php">Confidentialité</a>
                <span class="separator">•</span>
                <a href="<?php echo BASE_URL; ?>terms.php">Conditions d'utilisation</a>
                <span class="separator">•</span>
                <a href="<?php echo BASE_URL; ?>contact.php">Contact</a>
            </p>
        </div>
        
        <div class="footer-right">
            <div class="system-info">
                <span class="server-time">
                    <i class="fas fa-clock"></i>
                    <span id="serverTime"><?php echo date('H:i'); ?></span>
                </span>
                <span class="server-status">
                    <i class="fas fa-circle text-success"></i>
                    <span>Système en ligne</span>
                </span>
            </div>
        </div>
    </div>
</footer>