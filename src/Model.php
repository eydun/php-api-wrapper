<?php

namespace Starif\ApiWrapper;

use ArrayAccess;
use Exception;
use JsonSerializable;
use Starif\ApiWrapper\Concerns\HasAttributes;
use Starif\ApiWrapper\Concerns\HasRelationships;

abstract class Model implements ArrayAccess, JsonSerializable
{

    use HasAttributes;
    use HasRelationships;

    /**
     * The entity model's name on Api.
     *
     * @var string
     */
    protected $entity;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var Api
     */
    protected $api;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;


    /**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
    public $wasRecentlyCreated = false;

    /**
     * @return Api
     */
    public function getApi(): Api
    {
        return $this->api;
    }

    /**
     * @return string|null
     */
    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function __construct($fill = [], $exists = false)
    {
        $this->exists = $exists;
        $this->fill($fill);
        $this->syncOriginal();
        $this->boot();
    }

    public function boot()
    {
        //$this->api = new Api(/*...*/);
    }

    public static function find($id)
    {
        if (is_array($id)) {
            return self::where(['id' => $id]);
        }

        $instance = new static;
        return new static($instance->getApi()->{'get'.ucfirst($instance->getEntity())}($id), true);
    }

    /**
     * @param      $field
     * @param null $value
     * @return self[]
     */
    public static function where($field, $value = null)
    {
        if (!is_array($field)) {
            $field = [$field => $value];
        }

        $instance = new static;
        $entities = $instance->getApi()->{'get'.ucfirst($instance->getEntity()).'s'}($field);
        return array_map(function ($entity) {
            return new static($entity, true);
        }, $entities['data']);
    }

    /**
     * @return self[]
     */
    public static function all()
    {
        return static::where([]);
    }

    public static function create(array $attributes = [])
    {
        return (new static($attributes))->save();
    }

    /**
     * Fills the entry with the supplied attributes.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function fill(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if(is_array($value) && method_exists($this, $key)){
                $this->setRelation($key,
                    $this->$key()->getRelationsFromArray($value)
                );
            }
            else{
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int $options
     * @return string
     *
     * @throws \Exception
     */
    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->primaryKey;
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function getRouteKey()
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @return self|null
     */
    public function resolveRouteBinding($value)
    {
        return self::find($value);
    }

    /**
     * Convert a value to studly caps case.
     *
     * @param  string $value
     * @return string
     */
    public static function studly($value)
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save()
    {
        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ? $this->performUpdate() : true;
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert();
        }

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->syncOriginal();
        }

        return $saved;
    }

    public function update(array $attributes = [])
    {
        $this->fill($attributes)->save();

        return $this;
    }


    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        if (is_null($this->getKeyName())) {
            throw new Exception('No primary key defined on model.');
        }

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return false;
        }

        $this->performDeleteOnModel();

        return true;
    }

    /**
     * Perform a model update operation.
     *
     * @return bool
     */
    protected function performUpdate()
    {
        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $updatedField = $this->api->{'update'.ucfirst($this->getEntity())}($this->{$this->primaryKey}, $dirty);
            $this->fill($updatedField);
            $this->syncChanges();
        }

        return true;
    }


    /**
     * Perform a model insert operation.
     *
     * @return bool
     */
    protected function performInsert()
    {
        $attributes = $this->attributes;

        $updatedField = $this->api->{'create'.ucfirst($this->getEntity())}($attributes);
        $this->fill($updatedField);
        $this->exists = true;
        $this->wasRecentlyCreated = true;


        return true;
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function performDeleteOnModel()
    {
        $this->api->{'delete'.ucfirst($this->getEntity())}($this->{$this->primaryKey});

        $this->exists = false;
    }
}