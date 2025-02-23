        <a id="top"></a>
        <div id="logo" @click="goToTop('<?php echo getProjectRoot(); ?>', '<?php echo getIndexPath(); ?>')" title="<?php echo lang('returnHome'); ?>"></div>
        <div title="<?php echo lang('lightSwitch'); ?>" id="lightswitch" ref="lightswitch" @click="lightswitch()"></div>
        <header>
            <div id="breadcrumb">
                <div id="breadcrumb_normal"><?php print_breadcrumb(); ?></div>
                <div id="breadcrumb_index"><?php print_breadcrumb_index(); ?></div>
            </div>
            <h1 id="title"><?php if($PAGE->icon && !$PAGE->hideicon): ?><img src="<?php echo $PAGE->icon?>">&nbsp;<?php endif; ?><?php echo strip_tags($PAGE->title); ?></h1>
        </header>
        <main>
