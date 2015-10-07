<?php
namespace KissPHP;

abstract class AbstractModel
{
  protected $properties;

  protected $private_properties = [];

  public function __construct(\stdClass $properties)
  {
    $this->properties = $properties;
  }

  public function __set($property, $value)
  {
    if (!in_array($propety, $this->private_properties) && property_exists($this->properties, $property)) {
      $this->properties->{$property} = $value;
    }
    return $this;
  }

  public function __get($property)
  {
    if (!in_array($property, $this->private_properties)) {
      return $this->properties->$property;
    }
  }

  public function serialize()
  {
    $values = (array)$this->properties;
    foreach ($this->private_properties as $property) {
      unset($values[$property]);
    }
  }
}