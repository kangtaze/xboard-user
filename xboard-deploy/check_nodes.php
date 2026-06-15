<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$nodes = \XboardFerrari\Models\EdgetunnelNode::select('id','name','host')->limit(10)->get();
echo "ID | Name | Host\n";
echo "---|------|-----\n";
foreach($nodes as $n) {
    echo $n->id." | ".$n->name." | ".$n->host.PHP_EOL;
}