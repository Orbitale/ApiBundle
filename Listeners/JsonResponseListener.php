<?php

namespace Pierstoval\Bundle\ApiBundle\Listeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class JsonResponseListener implements EventSubscriberInterface {

    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Will force any response with the ApiController to have an "application/json" format
     *
     * @param FilterResponseEvent $event
     */
    public function onResponse(FilterResponseEvent $event)
    {
        $controller = $event->getRequest()->attributes->get('_controller');

        if (strpos($controller, 'Pierstoval\Bundle\ApiBundle\Controller\ApiController') !== false) {
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

        if (strpos($controller, 'Pierstoval\Bundle\ApiBundle\Controller\ApiController') !== false) {

            // Stops any other kernel.exception listener to occur
            $event->stopPropagation();

            $e = $event->getException();

            $data = array(
                'error' => true,
                'message' => $e->getMessage(),
                'exception' => array(
                    'code' => $e->getCode(),
                ),
            );

            if ($this->kernel->getEnvironment() !== 'prod') {
                $data['exception']['file'] = $e->getFile();
                $data['exception']['line'] = $e->getLine();
                $data['exception']['traceAsString'] = $e->getTraceAsString();
                $data['exception']['trace'] = $e->getTrace();
            }

            // Set a proper new response which will be JSON automatically
            $event->setResponse(new JsonResponse($data, 500));
        }
    }

    /**
     * {@inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onResponse'),
            KernelEvents::EXCEPTION => array('onException'),
        );
    }

}
