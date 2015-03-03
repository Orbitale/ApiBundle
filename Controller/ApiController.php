<?php

namespace Pierstoval\Bundle\ApiBundle\Controller;

use Pierstoval\Component\EntityMerger\EntityMerger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @Route("/", requirements={"serviceName":"([a-zA-Z0-9\._]?)+"})
 */
class ApiController extends FOSRestController
{
    private $services;
    private $service;

    /**
     * @Route("/{serviceName}", name="pierstoval_api_cget")
     * @Method({"GET"})
     *
     * @param string  $serviceName
     * @param Request $request
     *
     * @return View|Response
     */
    public function cgetAction($serviceName, Request $request)
    {
        $this->checkAsker($request);

        $service = $this->getService($serviceName);
        if ($service instanceof Response) {
            return $service;
        }

        $datas = $this->getDoctrine()->getManager()->getRepository($service['entity'])->findAll();

        $datas = array($serviceName => $datas);

        return $this->view($datas);
    }

    /**
     * @Route("/{serviceName}/{id}", requirements={"id": "\d+"}, defaults={"subElement": ""}, name="pierstoval_api_get")
     * @Route("/{serviceName}/{id}/{subElement}", requirements={"subElement": "([a-zA-Z0-9\._]/?)+", "id": "\d+"}, name="pierstoval_api_get_subrequest")
     * @Method({"GET"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param string  $subElement
     * @param Request $request
     *
     * @return View|Response
     */
    public function getAction($serviceName, $id, $subElement = null, Request $request)
    {
        $this->checkAsker($request);

        $service = $this->getService($serviceName);

        /** @var EntityRepository $repo */
        $repo = $this->getDoctrine()->getManager()->getRepository($service['entity']);

        // Fetch datas
        $data = $repo->find($id);

        // The entity key has it's trailing "s" removed
        $key = rtrim($serviceName, 's');

        if ($subElement) {
            $data = $this->fetchSubElement($subElement, $service, $data, $key);
            if ($data instanceof Response) {
                // Means we have an error, probably, so we send it to the browser
                return $data;
            }
        }

        if (!$data) {
            return $this->error('No item found with this identifier.');
        }

        $data = array($key => $data);

        return $this->view($data);
    }

    /**
     * @Route("/{serviceName}", requirements={"serviceName": "\w+"}, name="pierstoval_api_put")
     * @Method({"PUT"})
     *
     * @param string  $serviceName
     * @param Request $request
     *
     * @return View|Response
     */
    public function putAction($serviceName, Request $request)
    {
        $this->checkAsker($request);

        $service    = $this->getService($serviceName);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        // Generate a new object
        $object = new $service['entity'];

        $errors = $this->mergeObject($object, $request->request);

        if ($errors instanceof ConstraintViolationListInterface && count($errors)) {
            return $this->validationError($errors);
        }

        $id = $em->getUnitOfWork()->getSingleIdentifierValue($object);

        if ($id && $em->getRepository($service['entity'])->find($id)) {
            throw new \InvalidArgumentException('"PUT" method is used to insert new datas. If you want to merge object, use the "POST" method instead.');
        } else {
            $em->persist($object);
        }

        $em->flush();

        return $this->view(array('newObject' => $object));
    }

    /**
     * @Route("/{serviceName}/{id}", requirements={"id": "\d+"}, name="pierstoval_api_post")
     * @Method({"POST"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param Request $request
     *
     * @return View|Response
     */
    public function postAction($serviceName, $id, Request $request)
    {
        $this->checkAsker($request);
        $service = $this->getService($serviceName);

        /** @var EntityManager $em */
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository($service['entity']);

        // Get full item from database
        $object = $repo->find($id);

        if (!$object) {
            return $this->error('No item found with this identifier.');
        }

        $errors = $this->mergeObject($object, $request->request);

        if (count($errors)) {
            return $this->validationError($errors);
        }

        $em->merge($object);
        $em->flush();

        // We retrieve back the object from the database to get it full with relations
        return $this->view(array('newObject' => $repo->find($id)));
    }

    /**
     * @Route("/{serviceName}/{id}", requirements={"id": "\d+"}, name="pierstoval_api_delete")
     * @Method({"DELETE"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param Request $request
     *
     * @return View|Response
     */
    public function deleteAction($serviceName, $id, Request $request)
    {
        $this->checkAsker($request);

        $service = $this->getService($serviceName);

        $em = $this->getDoctrine()->getManager();

        // Fetch datas
        $data = $em->getRepository($service['entity'])->find($id);

        // The entity key has it's trailing "s" removed
        $key = rtrim($serviceName, 's').'.'.$id.'.old';

        if (!$data) {
            return $this->error('No item found with this identifier.');
        }

        $em->remove($data);
        $em->flush();

        $data = array($key => $data);

        return $this->view($data);
    }

    /**
     * @param Request $request
     */
    protected function checkAsker(Request $request)
    {
        $this->container->get('pierstoval.api.originChecker')->checkRequest($request);
    }

    /**
     * Retrieves a service name from the configuration
     *
     * @param string $serviceName
     * @param bool   $throwException
     *
     * @throws \InvalidArgumentException
     * @return null|string
     */
    protected function getService($serviceName = null, $throwException = true)
    {
        if (!$this->services) {
            $this->services = $this->container->getParameter('pierstoval_api.services');
        }
        if (null === $serviceName && $this->service) {
            return $this->service;
        }
        if (isset($this->services[$serviceName])) {
            $this->service = $this->services[$serviceName];
            return $this->services[$serviceName];
        }
        if ($throwException) {
            if ($this->container->get('kernel')->getEnvironment() === 'prod') {
                throw new \InvalidArgumentException($this->get('translator')->trans('Unrecognized service %service%', array('%service%' => $serviceName,)), 1);
            } else {
                throw new \InvalidArgumentException($this->get('translator')->trans(
                    "Service \"%service%\" not found in the API.\n".
                    "Did you forget to specify it in your configuration ?\n".
                    "Available services : %services%",
                    array('%service%' => $serviceName, '%services%' => implode(', ', array_keys($this->services)),)
                ), 1);
            }
        }
        return null;
    }

    /**
     * Returns a view by serializing its data, thanks to the FOSRestController
     *
     * @param mixed   $data
     * @param integer $statusCode
     * @param array   $headers
     *
     * @return View|Response
     */
    protected function view($data = null, $statusCode = null, array $headers = Array())
    {
        $view = parent::view($data, $statusCode, $headers);
        $view->setFormat('json');
        $view->setHeader('Content-type', 'application/json; charset=utf-8');

        return $this->handleView($view);
    }

    /**
     * @param ConstraintViolationListInterface $errors
     *
     * @return View|Response
     */
    protected function validationError(ConstraintViolationListInterface $errors)
    {
        return $this->view(array(
            'error'   => true,
            'message' => $this->get('translator')->trans('Invalid form, please re-check.', array(), 'pierstoval_api.exceptions'),
            'errors'  => $errors,
        ), 500);
    }

    /**
     * Handles a classic error (not an exception).
     * The difference between this method and an exception is that with this method you can specify HTTP code.
     *
     * @param string $message
     * @param array  $messageParams
     * @param int    $code
     *
     * @return View
     */
    protected function error($message = '', $messageParams = array(), $code = 404)
    {
        $message = $this->get('translator')->trans($message, $messageParams, 'pierstoval_api.exceptions');

        return $this->view(array(
            'error'   => true,
            'message' => $message,
        ), $code);
    }

    /**
     * Merges POST datas into an object, and returns validation result
     *
     * @param object       $object
     * @param ParameterBag $post
     *
     * @return ConstraintViolationListInterface
     */
    protected function mergeObject(&$object, ParameterBag $post)
    {
        // The user object has to be the "json" parameter
        $userObject = $post->has('json') ? $post->get('json') : null;

        if (!$userObject) {
            $msg = 'You must specify the "json" POST parameter.';
            return new ConstraintViolationList(array(
                new ConstraintViolation($msg, $msg, array(), '', null, '', ''),
            ));
        }
        if (is_string($userObject)) {
            // Allows either JSON string or array
            $userObject = json_decode($post->get('json'), true);
            if (!$userObject) {
                $msg = 'Error while parsing json.';
                return new ConstraintViolationList(array(
                    new ConstraintViolation($msg, $msg, array(), '', null, '', ''),
                ));
            }
        }

        $serializer = $this->container->get('serializer');

        if ($post->get('mapping')) {
            $entityMerger = new EntityMerger($this->getDoctrine()->getManager(), $serializer);
            try {
                $object = $entityMerger->merge($object, $userObject, $post->get('mapping'));
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                $propertyPath = null;
                if (strpos($msg, 'If you want to specify ') !== false) {
                    $propertyPath = preg_replace('~^.*If you want to specify "([^"]+)".*$~', '$1', $msg);
                }
                return new ConstraintViolationList(array(
                    new ConstraintViolation($msg, $msg, array(), '', $propertyPath, '', ''),
                ));
            }
        } else {
            // Transform the full item recursively into an array
            $object        = $serializer->deserialize($serializer->serialize($object, 'json'), 'array', 'json');
            $requestObject = json_decode($post->get('json'), true);

            // Merge the two arrays with request parameters
            $userObject = array_merge($object, $requestObject);

            // Serialize POST and deserialize to get full object
            $json   = $serializer->serialize($userObject, 'json');
            $object = $serializer->deserialize($json, $this->service['entity'], 'json');
        }

        return $this->get('validator')->validate($object);
    }

    /**
     * Parse the subelement request from "get" action in to get a fully "recursive" parameter check.
     *
     * @param array  $subElement
     * @param array  $service
     * @param mixed  $data
     * @param string $key
     *
     * @return mixed
     * @throws \Exception
     */
    protected function fetchSubElement($subElement, $service, $data, &$key)
    {

        $elements = explode('/', trim($subElement, '/'));

        if (count($elements)) {
            $key .= '.'.$this->getPropertyValue('_id', $data, $service['name']);
        }

        foreach ($elements as $k => $element) {
            $key .= '.'.$element;
            if (is_numeric($element)) {
                // Get an element when subElement is "/element/{id}"
                $element = (int) $element;
                if (is_array($data) || $data instanceof \Traversable) {
                    $found = false;
                    foreach ($data as $searchingData) {
                        if ($this->getPropertyValue('_id', $searchingData) === $element) {
                            $found = true;
                            $data  = $searchingData;
                        }
                    }
                    if (!$found) {
                        return $this->error('Found no element with identifier "'.$element.'" in requested object.',
                            array(), 404);
                    }
                } else {
                    return $this->error('Identifier cannot be requested for a collection.');
                }
            } else {
                $data = $this->getPropertyValue($element, $data);
            }

        }

        return $data;
    }

    /**
     * Retrieves the value of a property in an object.
     * The special "_id" field retrieves the primary key.
     *
     * @param $field
     * @param $object
     *
     * @return int
     * @throws \Exception
     */
    protected function getPropertyValue($field, $object)
    {
        if (!is_object($object)) {
            throw new \Exception('Field "'.$field.'" cannot be retrieved as analyzed element is not an object.');
        }
        $metadatas = $this->getDoctrine()->getManager()->getClassMetadata(get_class($object));

        if ($field === '_id') {
            // Check for identifier
            return (int) (array_values($metadatas->getIdentifierValues($object))[0]);
        } else {
            // Check for any other field
            $service = $this->getService($field, false);
            if ($service) {
                return $this
                    ->getDoctrine()->getManager()
                    ->getRepository($service['entity'])
                    ->findBy(array(
                        $metadatas->getAssociationMappedByTargetField($field) => $this->getPropertyValue('_id', $object)
                    ));
            }
            if ($metadatas->hasField($field) || $metadatas->hasAssociation($field)) {
                $reflectionProperty = $metadatas->getReflectionClass()->getProperty($field);
                $reflectionProperty->setAccessible(true);

                $data = $reflectionProperty->getValue($object);
                if ($data instanceof PersistentCollection) {
                    $data = $data->getValues();
                }
                return $data;
            } else {
                throw new \Exception('Field "'.$field.'" does not exist in "'.(new \ReflectionClass($object))->getShortName().'" object');
            }
        }

    }
}
