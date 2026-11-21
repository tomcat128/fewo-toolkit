<?php

namespace FewoTk2\Block;

use ReflectionClass;
use ReflectionProperty;

/**
 * Standardwerte für den "Hinzufügen"-Dialog eines Blocks.
 *
 * Concrete stellt dem Formular die Felder eines Blocks nur beim Bearbeiten als
 * Variablen bereit. Beim Hinzufügen gibt es noch keinen Datensatz – ohne diesen
 * Schritt wären die Variablen im Formular undefiniert und die im Controller
 * hinterlegten Vorgaben (z. B. angehakte Optionen) würden ignoriert.
 */
trait AddDefaults
{
    /**
     * Übergibt die öffentlichen Eigenschaften des Block-Controllers an das Formular.
     */
    protected function setAddDefaults(): void
    {
        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && $property->getDeclaringClass()->getName() === static::class) {
                $this->set($property->getName(), $property->getValue($this));
            }
        }
    }
}
