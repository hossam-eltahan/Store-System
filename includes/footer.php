</div><!-- End Main Content Wrapper -->
    <script>window.APP_BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time(); ?>"></script>
</body>
</html>
