<?php
namespace maider;
/**
 * Provide a admin area view for the plugin
 */
?>

<style>

</style>

<div class="wrap">
    <h1> <?=  PLUGIN_NAME ?> Options</h1>
    <div>
        <form method="post" action="options.php">
			<?php
			// This prints out all hidden setting fields
			settings_fields( strtolower( PLUGIN_NAME). '-options-group' );
			do_settings_sections( strtolower( PLUGIN_NAME) . '-options' );
			submit_button();
			?>
        </form>
    </div>
</div>

