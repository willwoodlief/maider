<?php
namespace maider;
global $shortcut_content;


   try {
        $error = false;
   } catch (\Exception $e) {
	   $error = $e->getMessage() ." [ {$e->getFile()} {$e->getLine()} ]";
   }
?>

<?php if ($error) {  ?>
    <div style="font-size: large; font-weight: bold; border: red solid 1px ; padding: 1em; width: 100%; text-align: center">
        <?= $error ?>
    </div>
<?php } ?>




<div class=""  style="width: 100%; margin: auto">
   <?= $shortcut_content ?>
</div>



