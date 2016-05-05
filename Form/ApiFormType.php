<?php

namespace Orbitale\Bundle\ApiBundle\Form;

use Symfony\Component\Form\AbstractType;

abstract class ApiFormType extends AbstractType
{
    /**
     * @var string
     */
    private $entityName;

    /**
     * @var array
     */
    private $entityConfig;

    /**
     * @var string
     */
    private $httpMethod;

    /**
     * @var mixed
     */
    private $sentData;

    public function __construct($httpMethod, $sentData, $entityName, array $entityConfig)
    {
        $this->httpMethod   = $httpMethod;
        $this->sentData     = $sentData;
        $this->entityName   = $entityName;
        $this->entityConfig = $entityConfig;
    }

    /**
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @return array
     */
    public function getEntityConfig()
    {
        return $this->entityConfig;
    }

    /**
     * Can be `POST`, `PUT` or `PATCH`.
     *
     * @return string
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * Get data that may have been sent to the form.
     *
     * @return mixed
     */
    public function getSentData()
    {
        return $this->sentData;
    }

    public function getName()
    {
        return 'orbitale_api_form_type';
    }
}
