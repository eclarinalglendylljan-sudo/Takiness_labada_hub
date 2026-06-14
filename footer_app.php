        </main>

    </div><!-- /.main-content -->

</div><!-- /.app-shell -->

<script>
// Global minimal JS (modal helpers shared by pages)
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(function (m) {
            m.style.display = 'none';
        });
    }
});
</script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
