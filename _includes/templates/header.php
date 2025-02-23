<!-- 

██████╗ ██╗ █████╗ ███╗   ███╗███████╗████████╗██████╗ ██╗ ██████╗██╗  ██╗
██╔══██╗██║██╔══██╗████╗ ████║██╔════╝╚══██╔══╝██╔══██╗██║██╔════╝██║ ██╔╝
██║  ██║██║███████║██╔████╔██║█████╗     ██║   ██████╔╝██║██║     █████╔╝ 
██║  ██║██║██╔══██║██║╚██╔╝██║██╔══╝     ██║   ██╔══██╗██║██║     ██╔═██╗ 
██████╔╝██║██║  ██║██║ ╚═╝ ██║███████╗   ██║   ██║  ██║██║╚██████╗██║  ██╗
╚═════╝ ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝╚═╝ ╚═════╝╚═╝  ╚═╝

-->
<!DOCTYPE html>
<html lang="fr-ca">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta itemprop="digest" content="<?php echo ($PAGE->password ? md5($PAGE->password) : ''); ?>">
        <meta itemprop="lang" content="<?php echo $PAGE->lang; ?>">
        <meta property="og:locale" content="fr_ca">
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?php echo $PAGE->ogtags->title; ?>">
        <meta property="og:description" content="<?php echo $PAGE->ogtags->description; ?>">
        <meta property="og:url" content="<?php echo $PAGE->ogtags->url; ?>">
        <meta property="og:image" content="<?php echo $PAGE->ogtags->image; ?>">
        <link rel="icon" type="image/x-icon" href="<?php echo $PAGE->shared; ?>images/favicon.ico">
        <?php foreach($styles as $cssfile): ?><link rel="stylesheet" href="<?php echo $cssfile; ?>">
        <?php endforeach;?><script>const shared = '<?php echo $PAGE->shared; ?>';</script>
        <script src="<?php echo $PAGE->shared; ?>jscripts/highlight.min.js"></script>
        <script src="<?php echo $PAGE->shared; ?>jscripts/swiper-bundle.min.js"></script>
        <script src="<?php echo $PAGE->shared; ?>jscripts/vue.global.prod.js"></script>
        <script src="<?php echo $PAGE->shared; ?>jscripts/pxdoc.min.js"></script>
        <title><?php echo strip_tags($PAGE->title); ?></title>
    </head>
    <body>
        <script>document.body.classList.add(localStorage.getItem('darkmode') === 'true' ? 'dark' : 'light');</script>
