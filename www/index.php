<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Thomas Schoffelen
 */

require __DIR__ . '/vendor/autoload.php';
 
// Your App
$app = new Bullet\App();
$app->path('/magister', function() use ($app) {
    $handler = new \Magister\Handler();
    require('lib/Core/DefaultRouter.php');
});
$app->path('/somtoday', function() use ($app) {
    $handler = new \SOMtoday\Handler();
    require('lib/Core/DefaultRouter.php');
});

// Run the app
echo $app->run(new Bullet\Request());