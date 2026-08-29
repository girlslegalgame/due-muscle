<?php
ob_start();
?>
<div class="container" style="text-align: center; padding-top: 50px;">
    <h2>(作成中)</h2>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>