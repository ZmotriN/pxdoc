<?php

if(!$PAGE->type) return;
if(!$info = register_page_type($PAGE->type)) return;
if(empty($info['footer'])) return;
if(is_string($info['footer'])) $info['footer'] = [$info['footer']];

foreach($info['footer'] as $footer) {
    if(!$footerfile = realpath($footer)) continue;
    include($footer);
}
