<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Orbitale\Bundle\ApiBundle\Listeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class JsonResponseListener implements EventSubscriberInterface
{

    /**
     * @var string
     */
    private $debug;

    public function __construct($debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE  => ['onResponse', 1],
            KernelEvents::EXCEPTION => ['onException', 1],
        ];
    }

    /**
     * Will force any response with the ApiController to have an "application/json" format
     *
     * @param FilterResponseEvent $event
     */
    public function onResponse(FilterResponseEvent $event)
    {
        $controller = $event->getRequest()->attributes->get('_controller');

        if ($this->checkControllerClass($controller)) {
            $event->getResponse()->headers->set('Content-type', 'application/json', true);
        }

    }

    /**
     * Helps throwing exceptions with the ApiController, by transforming the exception into a JSON object
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onException(GetResponseForExceptionEvent $event)
    {
        $controller = $event->getRequest()->attributes->get('_controller');

        if ($this->checkControllerClass($controller)) {

            $e = $event->getException();

            // Add all exceptions in the output data.
            $data = [
                'error' => true,
                'data'  => [],
            ];

            do {
                $current = [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ];

                // Add more informations while in debug mode.
                if ($this->debug) {
                    $current['file']     = $e->getFile();
                    $current['line']     = $e->getLine();
                    $current['asString'] = $e->getTraceAsString();
                    $current['full']     = $e->getTrace();
                }

                $data['data'] = $current;
            } while ($e = $e->getPrevious());

            $code = 500;
            // TODO: Add support to change the code depending on the exception.

            // Set a proper new response which will be JSON automatically
            $event->setResponse(new JsonResponse($data, $code));
        }
    }

    /**
     * Checks that the provided class is an instance of Orbitale's controller.
     *
     * @param string $controller
     *
     * @return bool
     */
    private function checkControllerClass($controller)
    {
        $orbitaleController = 'Orbitale\Bundle\ApiBundle\Controller\ApiController';

        return $controller === $orbitaleController || is_subclass_of($controller, $orbitaleController);
    }
}
