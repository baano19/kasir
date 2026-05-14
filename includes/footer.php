</div> <script>
    function toggleMenu() { 
        document.getElementById('sidebar').classList.toggle('active'); 
    }

    // Klik di luar sidebar buat nutup menu mobile
    document.addEventListener('click', function(event) {
        var sidebar = document.getElementById('sidebar');
        var burger = document.querySelector('.burger-btn');
        if (sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !burger.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>
</body>
</html>
