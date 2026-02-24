<?php
/**
 * Footer Template
 */
?>
            <!-- Content End -->
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <!-- Modal Container for dynamic modals -->
    <div id="modalContainer"></div>
    
    <script>
        // Global variables
        const BASE_URL = '';
        const CURRENT_USER = <?php echo json_encode($currentUser ?? null); ?>;
    </script>
</body>
</html>