<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

use Doctrine\Common\Annotations\AnnotationRegistry;

$file = __DIR__.'/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite.');
}
$autoload = require_once $file;

AnnotationRegistry::registerLoader(function($class) use ($autoload) {
    $autoload->loadClass($class);
    return class_exists($class, false);
});

require __DIR__.'/Fixtures/App/AppKernel.php';
