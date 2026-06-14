    </main>

</div><!-- /.staff-shell -->

<script>
    // Real-time clock for staff topbar
    (function updateClock() {
        const el = document.getElementById('staff-clock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleString('en-PH', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        setTimeout(updateClock, 30000);
    })();
</script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
