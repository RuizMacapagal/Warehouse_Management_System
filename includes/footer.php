<!-- Footer -->
    <footer class="mt-5 py-3 text-center text-muted">
        <div class="container">
            <p>© <?php echo date('Y'); ?> Warehouse Management System</p>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').DataTable();
            
            // Sidebar toggle for mobile
            $('#sidebarToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('active');
            });
        });
    </script>
</body>
</html>
