<!-- Toastr Scripts -->
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

<script>
    $(document).ready( function () {
        <?php if(isset($_SESSION['toastr_message']) && isset($_SESSION['toastr_type'])): ?>
        showToast("<?php echo $_SESSION['toastr_type']; ?>", "<?php echo $_SESSION['toastr_message']; ?>");
        <?php 
            // Unset the session variables after use
            unset($_SESSION['toastr_message']);
            unset($_SESSION['toastr_type']);
        ?>
        <?php endif; ?>

        function showToast(type, message) {
            toastr.options = {
                "closeButton": true, // Show close button
                "progressBar": true, // Show progress bar
                "timeOut": "5000",   // Duration time in milliseconds (3 seconds)
                "extendedTimeOut": "5000", // Extend duration time (3 seconds)
                "positionClass": "toast-top-right", // Toast position (top-right, top-left, bottom-right, bottom-left)
                "showDuration": "300", // Show animation time
                "hideDuration": "1000", // Hide animation time
                "showEasing": "swing", 
                "hideEasing": "linear",
                "showMethod": "fadeIn", 
                "hideMethod": "fadeOut"
            };

            switch(type) {
                case 'success':
                    toastr.success(message);
                break;
                case 'error':
                    toastr.error(message);
                break;
                case 'info':
                    toastr.info(message);
                break;
                case 'warning':
                    toastr.warning(message);
                break;
                default:
                    toastr.info(message);
                break;
            }
        }
    });
</script>