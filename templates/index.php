<?php
\OCP\Util::addScript('majordomo', 'majordomo');
?>

<span id="majordomo-app-context" data-app-context="<?php echo htmlentities(json_encode($appContext))?>" style="display: none"></span>
<div id="content"></div>
