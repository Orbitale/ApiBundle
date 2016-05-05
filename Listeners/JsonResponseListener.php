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
use Symfony\Component\HttpKernel\Exception\HttpException;
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
     * Will force any response from the ApiController to have an "application/json" format.
     *
     * @param FilterResponseEvent $event
     */
    public function onResponse(FilterResponseEvent $event)
    {
        if ($this->checkControllerClass($event->getRequest()->attributes->get('_controller'))) {
            $event->getResponse()->headers->set('Content-type', 'application/json', true);
        }
    }

    /**
     * Helps throwing exceptions with the ApiController, by transforming the exception into a JSON object.
     * This is cool because now you don't have to worry about returning responses with errors, etc.
     *
     * @todo Add specific exceptions classes.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onException(GetResponseForExceptionEvent $event)
    {
        // If we're in debug mode, we keep the native Symfony behavior.
        // This allows better understanding of the request itself, with profiler, wdt, etc.
        if (!$this->debug) {
            return;
        }

        if ($this->checkControllerClass($event->getRequest()->attributes->get('_controller'))) {

            $e = $event->getException();

            // Add all exceptions in the output data.
            $output = [
                'error' => true,
                'info'  => $e->getMessage(),
                'data'  => [],
            ];

            // By default, the code is 500.
            // But for any HttpException, the code is changed to the said exception's HTTP code.
            $code = 500;

            do {
                $current = [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ];

                // Change the status code if is an HttpException.
                if ($e instanceof HttpException && $e->getStatusCode()) {
                    $code = $e->getStatusCode();
                }

                $output['data'] = $current;
            } while ($e = $e->getPrevious());

            // In case the code is procesed manually instead of in the headers.
            $output['code'] = $code;

            // Set a proper new response which will be JSON automatically
            $event->setResponse(new JsonResponse($output, $code));
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
        return is_a($controller, 'Orbitale\Bundle\ApiBundle\Controller\ApiController', true);
    }
}
