<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Orbitale\Bundle\ApiBundle\Tests\Fixtures\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

$file = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite.');
}
$autoload = require_once $file;

// Cleans the "build" dir
if (is_dir(__DIR__.'/../build')) {
    echo "Removing files in the build directory.\n".__DIR__."\n";
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../build/', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
} else {
    mkdir(__DIR__.'/../build', 0775, true);
}

AnnotationRegistry::registerLoader(function($class) use ($autoload) {
    $autoload->loadClass($class);
    return class_exists($class, false);
});

require __DIR__.'/Fixtures/App/AppKernel.php';

$kernel = new AppKernel('test', true);
$kernel->boot();

$application = new Application($kernel);

// Create database
$command = new CreateDatabaseDoctrineCommand();
$application->add($command);
$command->run(new ArrayInput(array('command' => 'doctrine:database:create')), new NullOutput());

// Create database schema
$command = new CreateSchemaDoctrineCommand();
$application->add($command);
$command->run(new ArrayInput(array('command' => 'doctrine:schema:create')), new NullOutput());

$kernel->shutdown();
