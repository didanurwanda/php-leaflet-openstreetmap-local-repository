<?php

require 'vendor/autoload.php';

$app = new \Slim\Slim();
$dbh = new \PDO('sqlite:'. dirname(__FILE__) .'/repository.db');

$app->get('/tile/:s/:z/:x/:y', function ($s, $z, $x, $y) use ($app, $dbh) {
    $stmt = $dbh->prepare('SELECT * FROM repo WHERE s = ? AND z = ? AND x = ? AND y = ?');
    $stmt->bindParam(1, $s, \PDO::PARAM_STR);
    $stmt->bindParam(2, $z, \PDO::PARAM_STR);
    $stmt->bindParam(3, $x, \PDO::PARAM_STR);
    $stmt->bindParam(4, $y, \PDO::PARAM_STR);
    $stmt->execute();
    $fetch = $stmt->fetch(\PDO::FETCH_OBJ);
    
    $image = null;
    
    if (is_object($fetch)) {
        $image = $fetch->image;
    } else {
        // download
        $imageUrl = strtr('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', [
            '{s}' => (string) $s,
            '{z}' => (string) $z,
            '{x}' => (string) $x,
            '{y}' => (string) $y
        ]);
        $image = file_get_contents($imageUrl, false, stream_context_create([
            "http" => [
                "header" => "Host: a.tile.openstreetmap.org\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:73.0) Gecko/20100101 Firefox/73.0\r\nAccept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\nAccept-Language: en-US,en;q=0.5\r\nAccept-Encoding: gzip, deflate, br\r\nConnection: close\r\nUpgrade-Insecure-Requests: 1"
            ]
        ]));
        
        if ($image) {
            $insert = $dbh->prepare('INSERT INTO repo(s, z, x, y, image) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$s, $z, $x, $y, $image]);
        }
    }
    
    if ($image !== null) {
        $app->response->headers->set('Content-Type', 'image/png');
        $app->response->headers->set('Content-Disposition', "inline;filename=map.png");
        $app->response->headers->set('Content-Transfer-Encoding', 'binary');

        echo $image;
    }
});
$app->run();