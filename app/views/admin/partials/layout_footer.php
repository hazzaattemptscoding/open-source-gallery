    </div>
  </div>
</div>
<script src="/assets/js/admin-common.js" defer></script>
<?php /* Both load on every admin page, not just the upload page: an upload
        started elsewhere keeps running in the Service Worker, and the monitor
        is what draws its progress bar wherever the admin navigates to. */ ?>
<script src="/assets/js/upload-store.js" defer></script>
<script src="/assets/js/upload-monitor.js" defer></script>
</body>
</html>
