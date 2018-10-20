<?php
namespace maider;

/** @noinspection PhpIncludeInspection */
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../public/maider/config/class-config.php';
/** @noinspection PhpIncludeInspection */
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../lib/JsonHelper.php';
$messages = [];
try {
	$home_site = $home_url = '';
	//open up the configs
	$config_yaml_path = realpath( dirname( __FILE__ ) . "/../../config/config.yaml" );
	if ( ! $config_yaml_path ) {
		throw new \Exception( "Cannot find the config file path at ../../config/config.yaml " );
	}

	$config_class = new Config($config_yaml_path,'setup');
    $config_all = $config_class->get_config();
    if (!array_key_exists('this_plugin',$config_all)) {
        throw new \Exception("Config Block does not have a this_plugin section");
    }
    $this_plugin_block = $config_all['this_plugin'];

	if (!array_key_exists('master_name',$this_plugin_block)) {
		throw new \Exception("Config does not have a home site name set");
	}

	if (!array_key_exists('master_url',$this_plugin_block)) {
		throw new \Exception("Config does not have a home site url set");
	}

	$home_site = $this_plugin_block['master_name'];
	$home_url = $this_plugin_block['master_url'];



} catch (\Exception $e) {
    $messages[] = $e->getMessage();
}

?>

<style>

</style>

<div class="wrap">
    <h1> <?=  PLUGIN_NAME ?> Automatic Setup
        <button type="button" style="margin-left: 3em" id="maider-do-update" class="button action">
            <span>
                Update
            </span>

        </button>
        <div style="display: inline-block;padding-left: 1em;" >
            <div class="maider-load-icon"  style="display: none"  >
                <img src="../wp-content/plugins/maider/admin/css/spinner.gif">
            </div>
        </div>

    </h1>
    <?php if (sizeof($messages) > 0) {
        foreach ($messages as $message) { ?>
        <h4 style="background-color: red;color: white"><?= $message ?></h4>
    <?php } } ?>

    <div class="maider-already-run" style="display: none">
        <p> All of the instructions set in this plugin's configeration has run. Below is the results of these instructions.</p>
        <p> To have this plugin run different instructions, please remove this plugin, and create a new one to be installed at
            <a href="<?= $home_url ?>"> <?= $home_site ?></a>
        </p>
    </div>
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

