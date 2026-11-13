        </div><!-- /.page-container -->

        <!-- System Footer -->
        <footer class="text-center py-3 text-muted small border-top bg-white mt-auto no-print">
            <div>AgriTrack Agriculture Information System &copy; 2026 AP3 Midterm Project</div>
            <div style="font-size: 0.75rem;">Cebu Technological University · Normalized 3NF Database with Supabase PostgreSQL</div>
        </footer>
    </div><!-- /.app-main -->
</div><!-- /.app-wrapper -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom App Script -->
<script src="assets/js/app.js"></script>
<!-- ── Dark / Light Mode ──────────────────────────────────────── -->
<script>
(function () {
    // Apply saved theme before paint to avoid flash
    var saved = localStorage.getItem('agritrack_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);

    function updateThemeIcon(theme) {
        var btn  = document.getElementById('themeToggleBtn');
        var icon = document.getElementById('themeIcon');
        if (!btn || !icon) return;
        if (theme === 'dark') {
            icon.className = 'bi bi-sun-fill';
            btn.title = 'Switch to Light Mode';
        } else {
            icon.className = 'bi bi-moon-fill';
            btn.title = 'Switch to Dark Mode';
        }
    }

    window.toggleTheme = function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next    = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('agritrack_theme', next);
        updateThemeIcon(next);
    };

    // Re-apply icon on DOMContentLoaded (icons may not exist on first IIFE run)
    document.addEventListener('DOMContentLoaded', function () {
        updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
    });
})();
</script>
</body>
</html>
