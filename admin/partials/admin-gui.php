<?php
namespace maider;

/** @noinspection PhpIncludeInspection */
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../public/maider/config/class-config.php';
/** @noinspection PhpIncludeInspection */
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../lib/JsonHelper.php';
$messages = [];
$combined = [];
try {
	//open up the configs
	$config_yaml_path = realpath( dirname( __FILE__ ) . "/../../config/config.yaml" );
	if ( ! $config_yaml_path ) {
		throw new \Exception( "Cannot find the config file path at ../../config/config.yaml " );
	}

	$config = new Config($config_yaml_path,'setup');




} catch (\Exception $e) {
    $messages[] = $e->getMessage();
}

?>

<style>

</style>

<div class="wrap">
    <h1> <?=  PLUGIN_NAME ?> Automatic Setup <button type="button" style="margin-left: 3em" id="maider-do-update" class="button action"><span>Update</span></button></h1>
    <?php if (sizeof($messages) > 0) {
        foreach ($messages as $message) { ?>
        <h4 style="background-color: red;color: white"><?= $message ?></h4>
    <?php } } ?>

    <table class="wp-list-table widefat maider-option-table">
        <thead>
            <tr>
                <th scope="col"  class="manage-column column-name column-primary">Type</th>
                <th scope="col"  class="manage-column column-name column-primary">Name</th>
                <th scope="col"  class="manage-column column-name column-primary">Value</th>
                <th scope="col"  class="manage-column column-description">Results</th>
            </tr>
        </thead>
        <tbody>

        </tbody>
    </table>
</div>

