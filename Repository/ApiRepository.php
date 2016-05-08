<?php
/*
 * This file is part of the OrbitaleApiBundle package.
 *
 * (c) Alexandre Rock Ancelet <contact@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Bundle\ApiBundle\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * These default functions return an array and are easily overridable.
 * The goal is to let users define their own repository and just extend this one.
 */
class ApiRepository extends EntityRepository implements ApiRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findAllForApi()
    {
        return $this
            ->createQueryBuilder('entity')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneForApi($id)
    {
        return $this
            ->createQueryBuilder('entity')
            ->where('entity.'.$this->getClassMetadata()->getSingleIdentifierFieldName().' = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function findSubElement($entityName, $id, array $subElements)
    {
        $processedElements = array(
            $entityName, $id
        );

        /** @var object $value */
        $value = $this->find($id);

        $accessor = PropertyAccess::createPropertyAccessor();

        // Loop through all subElements,
        // and try to find the "final" value.
        do {
            $key = array_shift($subElements);

            $processedElements[] = $key;

            // Numeric keys will search for IDs in a collection.
            // String keys will be looked up with the PropertyAccessor
            if (is_numeric($key)) {

                if (is_array($value) || $value instanceof \Traversable) {

                    foreach ($value as $traversedKey => $traversedValue) {

                        if (!is_numeric($traversedKey) && ((string) $traversedKey) === ((string) $key)) {
                            // Means we may have a plain array, so let's have fun and try to check for value!
                            $value = $traversedValue;
                            break;
                        }

                        if (is_object($traversedValue)) {

                            // Search for Doctrine-possible values.
                            $metadata = null;
                            try {
                                $metadata = $this->_em->getClassMetadata(get_class($traversedValue));

                                // Get element from its ID
                                $idField = $metadata->getSingleIdentifierFieldName();

                                $idValue = $this->getValue($accessor, $traversedValue, $idField, $processedElements);

                                if ($idValue && $idValue === $key) {
                                    $value = $traversedValue;
                                    break;
                                }
                            } catch (\Exception $e) {
                                // Means object is not supported by Doctrine
                            }

                            // At this state, it means we found nothing.
                            // So, what to do?
                            // Well, property accessor...
                            $value = $this->getValue($accessor, $value, $key, $processedElements);

                        } else {
                            $value = $this->getValue($accessor, $value, $key, $processedElements);
                        }

                    }

                } else {
                    // If there are other elements, will automatically throw an exception.
                    $value = null;
                }

            } else {
                // Classic string key
                $value = $this->getValue($accessor, $value, $key, $processedElements);
            }

        } while (count($subElements));

        // Finally, transform objects into arrays, in case of touching at doctrine entities.
        if (is_object($value)) {
            $serializer = new Serializer(array(new ObjectNormalizer()), array());
            $value = $serializer->normalize($value);

            // Remove potential Doctrine useless fields.
            unset(
                $value['__initializer__'],
                $value['__cloner__'],
                $value['__isInitialized__']
            );
        }

        return $value;
    }

    /**
     * @param PropertyAccessor $accessor
     * @param object $object
     * @param string $key
     * @param array  $path
     *
     * @return mixed
     */
    private function getValue(PropertyAccessor $accessor, $object, $key, array $path)
    {
        // If method does not exist, then we use the PropertyAccessor.
        if ($accessor->isReadable($object, $key)) {
            return $accessor->getValue($object, $key);
        } else {
            throw new \RuntimeException(sprintf(
                'Property path "%s" does not exist in this item.',
                implode('.', $path)
            ));
        }
    }
}
