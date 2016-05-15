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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Orbitale\Bundle\ApiBundle\Repository\ApiRepository;
use Orbitale\Bundle\ApiBundle\Repository\ApiRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
     * @return JsonResponse
     */
    public function indexAction()
    {
        $services = $this->container->getParameter('orbitale_api.services');

        $data = [];

        foreach ($services as $serviceName => $service) {
            $serviceData = [
                'name'     => $serviceName,
                'root_url' => $this->generateUrl('orbitale_api_service_info', ['serviceName' => $serviceName], UGI::ABSOLUTE_URL),
                'entities' => [],
            ];
            foreach ($service['entities'] as $entityName => $entity) {
                $serviceData['entities'][] = [
                    'name'      => $entityName,
                    'url'       => $this->generateUrl('orbitale_api_get_collection', [
                        'serviceName' => $serviceName,
                        'entity'      => $entityName,
                    ], UGI::ABSOLUTE_URL),
                    'uses_form' => (bool)$entity['form_type'],
                ];
            }
            $data[] = $serviceData;
        }

        return $this->makeResponse([
            'info' => $this->get('translator')->trans('infos.services_list'),
            'data' => $data,
        ]);
    }


    /**
     * @param string $serviceName
     *
     * @return JsonResponse
     */
    public function serviceInfoAction($serviceName)
    {
        $this->initialize($serviceName);

        $serviceData = [
            'name'     => $serviceName,
            'root_url' => $this->generateUrl('orbitale_api_service_info', ['serviceName' => $serviceName], UGI::ABSOLUTE_URL),
            'entities' => [],
        ];
        foreach ($this->currentService['entities'] as $entityName => $entity) {
            $serviceData['entities'][] = [
                'name'      => $entityName,
                'url'       => $this->generateUrl('orbitale_api_get_collection', [
                    'serviceName' => $serviceName,
                    'entity'      => $entityName,
                ], UGI::ABSOLUTE_URL),
                'uses_form' => (bool)$entity['form_type'],
            ];
        }

        return $this->makeResponse([
            'info' => $this->get('translator')->trans('infos.service_info'),
            'data' => $serviceData,
        ]);
    }

    /**
     * @param string $serviceName
     * @param string $entity
     *
     * @return JsonResponse
     */
    public function getCollectionAction($serviceName, $entity)
    {
        $this->initialize($serviceName, $entity);

        $data = $this->getRepository($this->entity['class'])->findAllForApi();

        return $this->makeResponse([
            'data' => $data,
            'info' => count($data) ? '' : $this->get('translator')->trans('errors.no_item_found'),
        ]);
    }

    /**
     * @param string $serviceName
     * @param string $entity
     * @param string $id
     *
     * @return JsonResponse
     */
    public function getItemAction($serviceName, $entity, $id)
    {
        $this->initialize($serviceName, $entity);

        $data = $this->getRepository($this->entity['class'])->findOneForApi($id);

        if (!$data) {
            throw new NotFoundHttpException($this->get('translator')->trans('errors.no_item_found'));
        }

        return $this->makeResponse([
            'data' => $data,
        ]);
    }

    /**
     * @param string $serviceName
     * @param string $entity
     * @param string $id
     * @param string $subElement
     *
     * @return JsonResponse
     */
    public function getSubElementAction($serviceName, $entity, $id, $subElement)
    {
        $this->initialize($serviceName, $entity);

        $data = $this->getRepository($this->entity['class'])->findSubElement($entity, $id, explode('/', $subElement));

        return $this->makeResponse([
            'data' => $data,
            'info' => count($data) ? '' : $this->get('translator')->trans('errors.no_item_found'),
        ]);
    }

    /**
     * @param string  $serviceName
     * @param string  $entity
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function postAction($serviceName, $entity, Request $request)
    {
        $this->initialize($serviceName, $entity);

        $data = [];

        $formTypeClass = $this->entity['form_type'];

        if (!$formTypeClass) {
            $formTypeClass = 'Orbitale\Bundle\ApiBundle\Form\ApiFormType';
        }

        $object = new $this->entity['class']();
        $form = $this->get('form.factory')->createNamedBuilder($this->entity['name'], $formTypeClass, $object);

        // TODO

        return $this->makeResponse([
            'info' => 'To do',
            /*
            'data' => $data,
            'info' => [
                $this->get('translator')->trans('form.posted_successfully'),
                $this->get('translator')->trans('form.data_contain_new_element'),
            ],
            */
        ]);
    }

    /**
     * @-Route("/{serviceName}/{id}", requirements={"id": "\d+"}, name="orbitale_api_put")
     * @-Method({"PUT"})
     *
     * @param string  $serviceName
     * @param integer $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function putAction($serviceName, $id, Request $request)
    {
        /*
        $this->checkAsker($request);
        $service = $this->getService($serviceName);

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
        */
    }

    /**
     * @param string $serviceName
     * @param string $entity
     * @param string $id
     *
     * @return JsonResponse
     */
    public function deleteAction($serviceName, $entity, $id)
    {
        $this->initialize($serviceName, $entity);

        /** @var EntityManager $em */
        $em       = $this->getDoctrine()->getManager();
        $metadata = $em->getClassMetadata($this->entity['class']);
        $repo     = $em->getRepository($this->entity['class']);

        // Get the original object
        $data = $repo->find($id);

        // Get the array representation of this object without using serializer
        $arrayData = $repo
            ->createQueryBuilder('entity')
            ->where('entity.' . $metadata->getSingleIdentifierFieldName() . ' = :id')
            ->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)
        ;

        if (!$data) {
            throw new NotFoundHttpException($this->get('translator')->trans('errors.no_item_found'));
        }

        $em->remove($data);
        $em->flush();

        return $this->makeResponse([
            'data' => $arrayData,
            'info' => [
                $this->get('translator')->trans('infos.deleted_successfully'),
                $this->get('translator')->trans('infos.data_contain_deleted_element'),
            ],
        ]);
    }

    /**
     * @param string $id
     *
     * @return array
     */
    protected function getOneElement($id)
    {
        return $data;
    }

    /**
     * Returns a view by serializing its data
     *
     * @param array   $outputData
     * @param integer $statusCode
     * @param array   $headers
     *
     * @return JsonResponse
     */
    protected function makeResponse(array $outputData = [], $statusCode = null, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

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
     * Merges POST datas into an object, and returns validation result
     *
     * @param object       $object
     * @param ParameterBag $post
     *
     * @return ConstraintViolationListInterface
     */
    protected function mergeObject(&$object, ParameterBag $post)
    {
        /*
        // The user object has to be the "json" parameter
        $userObject = $post->has('json') ? $post->get('json') : null;

        if (!$userObject) {
            $msg = 'You must specify the "json" POST parameter.';

            return new ConstraintViolationList([
                new ConstraintViolation($msg, $msg, array(), '', null, '', ''),
            ]);
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

        $serializer = $this->get('serializer');

        if ($post->get('mapping')) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityMerger  = new EntityMerger($entityManager, $serializer);
            try {
                $object = $entityMerger->merge($object, $userObject, $post->get('mapping'));
            } catch (\Exception $e) {
                $msg          = $e->getMessage();
                $propertyPath = null;
                if (strpos($msg, 'If you want to specify ') !== false) {
                    $propertyPath = preg_replace('~^.*If you want to specify "([^"]+)".*$~', '$1', $msg);
                }

                return new ConstraintViolationList([
                    new ConstraintViolation($msg, $msg, [], '', $propertyPath, '', ''),
                ]);
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
        */
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

        /*
        $elements = explode('/', trim($subElement, '/'));

        if (count($elements)) {
            $key .= '.' . $this->getPropertyValue('_id', $data, $service['name']);
        }

        foreach ($elements as $k => $element) {
            $key .= '.' . $element;
            if (is_numeric($element)) {
                // Get an element when subElement is "/element/{id}"
                $element = (int)$element;
                if (is_array($data) || $data instanceof \Traversable) {
                    $found = false;
                    foreach ($data as $searchingData) {
                        if ($this->getPropertyValue('_id', $searchingData) === $element) {
                            $found = true;
                            $data  = $searchingData;
                        }
                    }
                    if (!$found) {
                        return $this->error('Found no element with identifier "' . $element . '" in requested object.',
                            [], 404);
                    }
                } else {
                    return $this->error('Identifier cannot be requested for a collection.');
                }
            } else {
                $data = $this->getPropertyValue($element, $data);
            }

        }

        return $data;
        */
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

    /**
     * @param string $entity
     *
     * @return ApiRepository
     */
    private function getRepository($entity)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $repo = $em->getRepository($entity);

        // Fallback to our own repository to be sure the default behavior can work.
        if (!($repo instanceof ApiRepositoryInterface)) {
            $repo = new ApiRepository($em, $em->getClassMetadata($entity));
        }

        return $repo;
    }
}
