<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use ReflectionClass;

/**
 * usage
 * ```php
 *     $myObject = ReflectionHelper::allowAccessToProperty(MyClass::class, 'myProtectedProperty');
 *     $myObject->myProtectedProperty = 'new value';
 * ```
 */

class ReflectionHelper
{
    /**
     * returns a new instance of a class with the protected property available for changing.
     *
     * @return object object of the classname provided
     */
    public static function allowAccessToProperty(string $className, string $propertyName)
    {
        // Create a reflection object for the class
        $reflectionClass = new ReflectionClass($className);

        // Get the protected property
        $property = $reflectionClass->getProperty($propertyName);

        // Make the property accessible
        $property->setAccessible(true);

        // Set the value of the protected property
        return new $className();
    }
}
