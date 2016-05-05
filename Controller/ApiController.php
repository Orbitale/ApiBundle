<?php
/*
 * This file is part of the OrbitaleApiBundle package.
 *
 * (c) Alexandre Rock Ancelet <contact@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Bundle\ApiBundle\Controller;

use Doctrine\ORM\AbstractQuery;
use Orbitale\Bundle\ApiBundle\Repository\ApiRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\PersistentCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as UGI;

class ApiController extends Controller
{

    /**
     * @var array
     */
    private $servicesList;

    /**
     * @var array
     */
    private $currentService;

    /**
     * @var array
     */
    private $entity;

    /**
     * @var string
     */
    private $subRequest;

    /**
     * @Route("/", name="orbitale_api_index")
     * @Method({"GET"})
     */
    public function indexAction()
    {
        $services = $this->container->getParameter('orbitale_api.services');

        $data = [];

        foreach ($services as $serviceName => $service) {
            $serviceData = [
                'name' => $serviceName,
                'root_url' => $this->generateUrl('orbitale_api_service_info', ['serviceName' => $serviceName], UGI::ABSOLUTE_URL),
                'entities' => [],
            ];
            foreach ($service['entities'] as $entityName => $entity) {
                $serviceData['entities'][] = [
                    'name' => $entityName,
                    'url' => $this->generateUrl('orbitale_api_cget', ['serviceName' => $serviceName, 'entity' => $entityName], UGI::ABSOLUTE_URL),
                    'uses_form' => (bool) $entity['form_type'],
                ];
            }
            $data[] = $serviceData;
        }

        return $this->makeResponse([
            'info'  => $this->get('translator')->trans('services_list'),
            'data'  => $data,
        ]);
    }


    /**
     * @Route("/{serviceName}", name="orbitale_api_service_info", requirements={"serviceName": "\w+"})
     * @Method({"GET"})
     *
     * @param string  $serviceName
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function serviceInfoAction($serviceName, Request $request)
    {
        $this->initialize($serviceName);

        $serviceData = [
            'name' => $serviceName,
            'root_url' => $this->generateUrl('orbitale_api_service_info', ['serviceName' => $serviceName], UGI::ABSOLUTE_URL),
            'entities' => [],
        ];
        foreach ($this->currentService['entities'] as $entityName => $entity) {
            $serviceData['entities'][] = [
                'name' => $entityName,
                'url' => $this->generateUrl('orbitale_api_cget', ['serviceName' => $serviceName, 'entity' => $entityName], UGI::ABSOLUTE_URL),
                'uses_form' => (bool) $entity['form_type'],
            ];
        }

        return $this->makeResponse([
            'info'  => $this->get('translator')->trans('services_list'),
            'data'  => $serviceData,
        ]);
    }

    /**
     * @Route("/{serviceName}/{entity}", name="orbitale_api_cget", requirements={"serviceName": "\w+", "entity": "\w+"})
     * @Method({"GET"})
     *
     * @param string  $serviceName
     * @param string  $entity
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function cgetAction($serviceName, $entity, Request $request)
    {
        $this->initialize($serviceName, $entity);

        /** @var EntityRepository|ApiRepository $repo */
        $repo = $this->getDoctrine()->getManager()->getRepository($this->entity['class']);

        if ($repo instanceof ApiRepository) {
            // Overridable
            $data = $repo->findAllForApi();
        } else {
            // Fallback
            $data = $repo->createQueryBuilder('entity')->getQuery()->getArrayResult();
        }

        return $this->makeResponse(array(
            'data' => $data,
            'info' => count($data) ? '' : $this->get('translator')->trans('no_item_found'),
        ));
    }

    /**
     * @Route(
     *     "/{serviceName}/{entity}/{id}",
     *     name="orbitale_api_get",
     *     requirements={
     *         "id": "\d+",
     *         "serviceName": "\w+",
     *         "entity": "\w+"
     *     }
     * )
     * @Method({"GET"})
     *
     * @param string  $serviceName
     * @param         $entity
     * @param integer $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getAction($serviceName, $entity, $id, Request $request)
    {
        $this->initialize($serviceName, $entity);

        $em = $this->getDoctrine()->getManager();

        /** @var EntityRepository|ApiRepository $repo */
        $repo = $em->getRepository($this->entity['class']);

        if ($repo instanceof ApiRepository) {
            // Overridable
            $data = $repo->findOneForApi($id);
        } else {
            // Fallback
            $data = $repo
                ->createQueryBuilder('entity')
                ->where('entity.'.$em->getClassMetadata($this->entity['class'])->getSingleIdentifierFieldName().' = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)
            ;
        }

        return $this->makeResponse(array(
            'data' => $data,
            'info' => count($data) ? '' : $this->get('translator')->trans('no_item_found'),
        ));
    }

    /**
     * @-Route("/{serviceName}/{entity}/{subElement}", requirements={"subElement": "([a-zA-Z0-9\._]/?)+", "id": "\d+"}, name="orbitale_api_get_subrequest")
     *
     * @param string$subElement
     */
    public function getSubElementAction($subElement)
    {
        // Todo
    }

    /**
     * @-Route("/{serviceName}", requirements={"serviceName": "\w+"}, name="orbitale_api_post")
     * @Method({"post"})
     *
     * @param string  $serviceName
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function postAction($serviceName, Request $request)
    {
        $this->checkAsker($request);

        $service = $this->getService($serviceName);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        // Generate a new object
        $object = new $service['entity'];

        $errors = $this->mergeObject($object, $request->request);

        if ($errors instanceof ConstraintViolationListInterface && count($errors)) {
            return $this->validationError($errors);
        }

        $id = $em->getUnitOfWork()->getSingleIdentifierValue($object);

        $repo = $em->getRepository($service['entity']);

        if ($id && $repo->find($id)) {
            throw new \InvalidArgumentException('"POST" method is used to insert new datas. If you want to edit an object, use the "PUT" method instead.');
        } else {
            $em->persist($object);
        }

        $em->flush();

        // Get the new object ID for full refresh
        $id = $em->getUnitOfWork()->getSingleIdentifierValue($object);

        return $this->makeResponse(array(
            'data' => $repo->find($id),
            'path' => rtrim($serviceName, 's').'.'.$id,
            'link' => $this->generateUrl('orbitale_api_get', array('id' => $id, 'serviceName' => $serviceName), UrlGeneratorInterface::ABSOLUTE_URL),
        ), 201);
    }

    /**
     * @-Route("/{serviceName}/{id}", requirements={"id": "\d+"}, name="orbitale_api_put")
     * @Method({"PUT"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function putAction($serviceName, $id, Request $request)
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
        return $this->makeResponse(array(
            'data' => $repo->find($id),
            'path' => rtrim($serviceName, 's').'.'.$id,
            'link' => $this->generateUrl('orbitale_api_get', array('id' => $id, 'serviceName' => $serviceName), UrlGeneratorInterface::ABSOLUTE_URL),
        ), 200);
    }

    /**
     * @-Route("/{serviceName}/{id}", requirements={"id": "\d+"}, name="orbitale_api_delete")
     * @Method({"DELETE"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deleteAction($serviceName, $id, Request $request)
    {
        $this->checkAsker($request);

        $service = $this->getService($serviceName);

        $em = $this->getDoctrine()->getManager();

        $data = $em->getRepository($service['entity'])->find($id);

        if (!$data) {
            return $this->error('No item found with this identifier.');
        }

        $em->remove($data);
        $em->flush();

        return $this->makeResponse(array('data' => $data, 'path' => rtrim($serviceName, 's') . '.' . $id));
    }

    /**
     * Returns a view by serializing its data
     *
     * @param array   $outputData
     * @param integer $statusCode
     * @param array   $headers
     *
     * @return Response
     */
    protected function makeResponse(array $outputData = [], $statusCode = null, array $headers = array())
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        $outputData['meta'] = [
            'service' => $this->currentService['name'],
            'entity' => $this->entity ? $this->entity['name'] : null,
            'path' => $this->currentService['name'].($this->entity ? ('.'.$this->entity['name']) : ''),
        ];

        if ($this->subRequest) {
            $outputData['meta']['path'] .= '.'.$this->subRequest;
        }

        if (!array_key_exists('error', $outputData)) {
            $outputData = array_merge(['error' => false], $outputData);
        }

        if (!array_key_exists('data', $outputData)) {
            $outputData['data'] = null;
        }

        if (!array_key_exists('info', $outputData)) {
            $outputData = array_merge(['info' => ''], $outputData);
        }

        return new JsonResponse($outputData, $statusCode ?: 200, $headers);
    }

    /**
     * @param ConstraintViolationListInterface $errors
     *
     * @return Response
     */
    protected function validationError(ConstraintViolationListInterface $errors)
    {
        return $this->makeResponse(array(
            'error'   => true,
            'message' => $this->get('translator')->trans('Invalid form, please re-check.', array(), 'orbitale_api.exceptions'),
            'errors'  => $errors,
        ), 400);
    }

    /**
     * Handles a classic error (not an exception).
     * The difference between this method and an exception is that with this method you can specify HTTP code.
     *
     * @param string $message
     * @param array  $messageParams
     * @param int    $code
     *
     * @return JsonResponse
     */
    protected function error($message = '', $messageParams = array(), $code = 404)
    {
        $message = $this->get('translator')->trans($message, $messageParams, 'orbitale_api.exceptions');

        return $this->makeResponse(array(
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
            /** @var ObjectManager $entityManager */
            $entityManager = $this->getDoctrine()->getManager();
            $entityMerger = new EntityMerger($entityManager, $serializer);
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
            $object = $serializer->deserialize($serializer->serialize($object, 'json'), 'array', 'json');

            // Merge the two arrays with request parameters
            $userObject = array_merge($object, $userObject);

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
        /** @var ClassMetadataInfo $metadatas */
        $metadatas = $this->getDoctrine()->getManager()->getClassMetadata(get_class($object));

        if ($field === '_id') {
            // Check for identifier
            $values = array_values($metadatas->getIdentifierValues($object));
            return (int) $values[0];
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
                $ref = new \ReflectionClass($object);
                throw new \Exception('Field "'.$field.'" does not exist in "'.$ref->getShortName().'" object');
            }
        }

    }

    /**
     * @param string $serviceName
     * @param string $entity
     */
    protected function initialize($serviceName, $entity = '')
    {
        $this->servicesList = $this->container->getParameter('orbitale_api.services');

        if (!array_key_exists($serviceName, $this->servicesList)) {
            throw $this->createNotFoundException(sprintf(
                'Service "%s" does not exists.',
                $serviceName
            ));
        }

        $this->currentService = $this->servicesList[$serviceName];

        if ($entity) {
            if (!array_key_exists($entity, $this->currentService['entities'])) {
                throw $this->createNotFoundException(sprintf(
                    'Entity "%s" does not exists in service "%s".',
                    $entity, $serviceName
                ));
            }

            $this->entity = $this->currentService['entities'][$entity];
        }
    }
}
