<?php

namespace App\Classes\Eloquent\Traits;

use Exception;
use App\Helpers\CryptHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Log;
use App\Classes\Eloquent\EncryptionEloquentBuilder;
use Illuminate\Support\Str;

trait EncriptionTrait
{
    private $columns = [];
    private $exclude_crypt_fields = [];
    private $enabled_rename_meta = false;
    private $enable_crypt_database = false;

    private $real_collection;

    public function __construct()
    {
        parent::__construct();
        try {
            $this->real_collection = $this->collection;
            $this->enable_crypt_database = config('crypt.database.enable', false);
            $this->enabled_rename_meta = config('crypt.database.rename_meta', false);
            if ($this->enable_crypt_database || $this->enabled_rename_meta) {
                $scheme = config('crypt.database.scheme.' . $this->collection, []);
                $this->columns = $scheme['fields'] ?? [];
                $this->exclude_crypt_fields = $scheme['exclude_crypt_fields'] ?? [];
                if ($this->enabled_rename_meta === true && !empty($scheme['collection'])) {
                    $this->collection = $scheme['collection'];
                }
            }

            // attributes
            if (!empty($this->attributes ?? [])) {
                if ($this->is_array_indexed($this->attributes)) {
                    // foreach ($this->attributes as $attribute) {
                    // $this->setAttribute($attribute, null);
                    foreach ($this->columns as $convention => $actual) {
                        if ($this->is_allow_rename($convention) && array_key_exists($convention, $this->attributes)) {
                            $this->attributes[] = $actual;
                            unset($this->attributes[$convention]);
                        }
                    }
                    // }
                } else {
                    foreach ($this->attributes as $attribute => $value) {
                        // $this->setAttribute($attribute, $value);
                        foreach ($this->columns as $convention => $actual) {
                            if ($attribute == $convention && $this->is_allow_rename($convention) && array_key_exists($convention, $this->attributes)) {
                                $this->attributes[$actual] = $this->encrypt($convention, $value);
                                unset($this->attributes[$convention]);
                            }
                        }
                    }
                }
            }

            // hidden
            if (!empty($this->hidden ?? [])) {
                if ($this->is_array_indexed($this->attributes)) {
                    // foreach ($this->attributes as $attribute) {
                    // $this->setAttribute($attribute, null);
                    foreach ($this->columns as $convention => $actual) {
                        if ($this->is_allow_rename($convention) && array_key_exists($convention, $this->hidden)) {
                            $this->hidden[] = $actual;
                            unset($this->hidden[$convention]);
                        }
                    }
                    // }
                } else {
                    foreach ($this->hidden as $attribute => $value) {
                        // $this->setAttribute($attribute, $value);
                        foreach ($this->columns as $convention => $actual) {
                            if ($this->is_allow_rename($convention) && array_key_exists($convention, $this->hidden)) {
                                $this->hidden[$actual] = $this->encrypt($convention, $value);
                                unset($this->hidden[$convention]);
                            }
                        }
                    }
                }
            }

            // cast
            if (!empty($this->cast ?? [])) {
                foreach ($this->columns as $convention => $actual) {
                    if ($this->is_allow_rename($convention) && array_key_exists($convention, $this->cast)) {
                        $this->cast[$actual] = $this->cast[$convention];
                        unset($this->cast[$convention]);
                    }
                }
            }

            // appends
            // if (!empty($this->appends ?? [])) {
            //     foreach ($this->columns as $convention => $actual) {
            //         if ($this->is_allow_rename($convention) && array_key_exists($convention, $this->appends)) {
            //             $this->appends[] = $actual;
            //             unset($this->appends[$convention]);
            //         }
            //     }
            // }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    /**
     * Is array associative or sequential?
     *
     * @param array $attributes
     * @return boolean
     */
    private function is_array_indexed(array $attributes): bool
    {
        return array_values($attributes) === $attributes;
    }

    /**
     * is_allow_rename function
     *
     * @param [type] $column
     * @return boolean
     */
    private function is_allow_rename($column): bool
    {
        if (
            $this->enabled_rename_meta === true &&
            is_string($column) &&
            !empty($column) &&
            !in_array($column, ['_id', 'clientId']) &&
            array_key_exists($column, $this->columns)
        ) {
            return true;
        }
        return false;
    }

    /**
     * is_allow_crypt function
     *
     * @param [type] $value
     * @return boolean
     */
    private function is_allow_crypt($column, $value): bool
    {
        if (
            $this->enable_crypt_database === true &&
            !in_array($column, $this->exclude_crypt_fields) &&
            array_key_exists($column, $this->columns) &&
            is_string($value) &&
            !empty($value) &&
            !is_numeric($value) &&
            !is_bool($value) &&
            !preg_match('/^[a-f\d]{24}$/i', $value)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Encrypt
     *
     * @param [type] $value
     * @return [type]
     */
    private function encrypt($column, $value)
    {
        if ($this->is_allow_crypt($column, $value)) {
            $encrypted_value = CryptHelper::encrypt($value);
            if (!empty($encrypted_value)) {
                return $encrypted_value;
            }
        }
        return $value;
    }

    /**
     * Decrypt function
     *
     * @param [type] $value
     * @return void
     */
    private function decrypt($column, $value)
    {
        if ($this->is_allow_crypt($column, $value)) {
            $decrypted_value = CryptHelper::decrypt($value);
            // GeneralHelper::PrintR(['decrypt', $value, $decrypted_value]);die();
            if (!empty($decrypted_value)) {
                return $decrypted_value;
            }
        }
        return $value;
    }

    protected function transformModelValue($key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        } elseif ($this->hasAttributeGetMutator($key)) {
            return $this->mutateAttributeMarkedAttribute($key, $value);
        }

        // decrypt value before casts
        if (is_string($key) && !empty($key) && !is_null($value) && $value !== ''/* && in_array($key, $this->encryptable)*/) {
            $value = $this->decrypt($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null
            && \in_array($key, $this->getDates(), false)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        foreach ($this->columns as $convention => $actual) {
            if (array_key_exists($actual, $attributes)) {
                $attributes[$convention] = $this->decrypt($convention, $attributes[$actual]);
                unset($attributes[$actual]);
            }
        }
        // if ($this->collection == 'm21') {
        //     GeneralHelper::PrintR($attributes);die();
        // }
        return $attributes;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArrayBak()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes, $mutatedAttributes = $this->getMutatedAttributes()
        );

        // decrypt attributes before casts
        // $attributes = $this->decryptAttributes($attributes);
        foreach ($this->columns as $convention => $actual) {
            if (array_key_exists($actual, $attributes)) {
                $attributes[$convention] = $this->decrypt($convention, $attributes[$actual]);
                unset($attributes[$actual]);
            }
        }

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes, $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        // Log::error('attributesToArray', [$attributes]);
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($real_key)
    {
        $key = $real_key;
        if ($this->is_allow_rename($real_key)) {
            $key = $this->columns[$real_key];
        }
        // if ($real_key == 'status_name') {
        //     GeneralHelper::PrintR(['getAttribute', $key]);
        //     die();
        // }
        // Log::error('getAttribute', [$real_key, $key]);
        return $this->decrypt($real_key, parent::getAttributeValue($key));
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($real_key, $value)
    {
        $key = $real_key;
        $value = $this->encrypt($real_key, $value);
        if ($this->is_allow_rename($real_key)) {
            $key = $this->columns[$real_key];
        }
        // if ($real_key == 'status_name') {
        //     GeneralHelper::PrintR(['setAttribute', $key]);
        //     die();
        // }
        // Log::error('setAttribute', [$real_key, $key, $value]);
        return parent::setAttribute($key, $value);
    }

    // public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    // {
    //     if (is_string($foreignKey) && !empty($foreignKey)) {
    //         if (array_key_exists($foreignKey, $this->columns)) {
    //             $foreignKey = $this->columns[$foreignKey];
    //         }
    //     }
    //     if (is_string($otherKey) && !empty($otherKey)) {
    //         if (array_key_exists($otherKey, $this->columns)) {
    //             $otherKey = $this->columns[$otherKey];
    //         }
    //     }
    //     // Log::error('belongsTo', [$related, $foreignKey, $otherKey]);
    //     return parent::belongsTo($related, $foreignKey, $otherKey, $relation);
    // }

    /**
     * Get the value of an "Attribute" return type marked attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttributeMarkedAttribute($key, $value)
    {
        if (array_key_exists($key, $this->attributeCastCache)) {
            return $this->attributeCastCache[$key];
        }

        $attribute = $this->{Str::camel($key)}();

        $_attributes = [];
        foreach ($this->attributes as $key => $value) {
            $real_key = array_search($key, $this->columns);
            if ($real_key !== false) {
                $value = $this->decrypt($real_key, $value);
                if ($this->is_allow_rename($real_key)) {
                    $_attributes[$real_key] = $value;
                } else {
                    $_attributes[$key] = $value;
                }
            } else {
                $_attributes[$key] = $value;
            }
        }

        $value = call_user_func($attribute->get ?: function ($value) {
            return $value;
        }, $value, $_attributes); //$this->attributes

        if ($attribute->withCaching || (is_object($value) && $attribute->withObjectCaching)) {
            $this->attributeCastCache[$key] = $value;
        } else {
            unset($this->attributeCastCache[$key]);
        }

        return $value;
    }

    /**
     * Set the value of a "Attribute" return type marked attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function setAttributeMarkedMutatedAttributeValue($key, $value)
    {
        $attribute = $this->{Str::camel($key)}();

        $callback = $attribute->set ?: function ($value) use ($key) {
            $this->attributes[$key] = $value;
        };

        $_attributes = [];
        foreach ($this->attributes as $key => $value) {
            $real_key = array_search($key, $this->columns);
            if ($real_key !== false) {
                $value = $this->decrypt($real_key, $value);
                if ($this->is_allow_rename($real_key)) {
                    $_attributes[$real_key] = $value;
                } else {
                    $_attributes[$key] = $value;
                }
            } else {
                $_attributes[$key] = $value;
            }
        }

        $this->attributes = array_merge(
            $this->attributes,
            $this->normalizeCastClassResponse(
                $key,
                $callback($value, $_attributes) //$this->attributes
            )
        );

        if ($attribute->withCaching || (is_object($value) && $attribute->withObjectCaching)) {
            $this->attributeCastCache[$key] = $value;
        } else {
            unset($this->attributeCastCache[$key]);
        }

        return $this;
    }

    // Extend EncryptionEloquentBuilder
    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        return new EncryptionEloquentBuilder($query, $this->real_collection);
    }
}
