<?php

namespace framework\db;

use framework\db\commands\DeleteCommand;
use framework\db\commands\SelectCommand;
use framework\models\attributes\PrimaryKey;
use framework\models\interfaces\BeforeSave;
use framework\models\Model;

class ActiveModel extends Model
{
    protected $relations = [];

    public function &__get($name)
    {
        if (!empty($this->relations[$name])) {
            return $this->relations[$name]['records'];
        } else if (method_exists($this, 'get_' . $name)) {
            $method = 'get_' . $name;
            $relation = $this->$method();

            $this->relations[$name] = [
                'relation' => $relation,
                'records' => $relation->get()
            ];

            return $this->relations[$name]['records'];
        }

        throw new \Exception("Property {$name} not found in model " . static::class);
    }

    public function __set($name, $value)
    {
        if (method_exists($this, 'get_' . $name)) {
            if (empty($this->relations[$name])) {
                $method = 'get_' . $name;
                $relation = $this->$method();
                $this->relations[$name] = [
                    'records' => $value,
                    'relation' => $relation
                ];
            } else if ($this->relations[$name]) {
                $this->relations[$name]['records'] = $value;
            }
        } else {
            $this->$name = $value;
        }
    }

    public static function table()
    {
        return strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
    }

    public static function primaryKey()
    {
        $data = static::getMetaData();

        foreach ($data as $prop => $meta) {
            foreach ($meta['attributes'] as $attr) {
                if ($attr->newInstance() instanceof PrimaryKey) {
                    return $prop;
                }
            }
        }

        return null;
    }

    public static function find($value, $column = null)
    {
        if (empty($column)) {
            $column = static::primaryKey();
        }

        $query = new SelectCommand(db()->conn(), static::columns());
        $query->from(static::table());
        $query->where($column, $value);

        $result = $query->first();

        if (empty($result)) return null;

        return self::from($result);
    }

    public static function all($columns = null)
    {
        if (empty($columns)) {
            $columns = static::columns();
        }
        $query = new SelectCommand(db()->conn(), $columns);
        $query->from(static::table());

        return array_map([static::class, 'from'], $query->all());
    }

    public static function select($columns = null)
    {
        if (empty($columns)) {
            $columns = static::columns();
        }
        $query = new SelectCommand(db()->conn(), $columns);
        $query->from(static::table());
        $query->transform = function ($data) {
            return static::from($data);
        };

        return $query;
    }

    public function delete()
    {
        $query = new DeleteCommand(db()->conn(), $this->table());
        $id = $this->primaryKey();
        $query->where($id, $this->$id);
        $query->execute();
        $this->$id = 0;
    }

    public function attributes()
    {
        $meta = static::getMetaData();
        $data = [];

        foreach ($meta as $prop => $info) {
            if ($info['initialized']($this)) {
                $value = $this->$prop;
                $type = $info['type'];

                if (isset(static::$typeTransformers[$type])) {
                    $data[$prop] = static::$typeTransformers[$type]->transformToDatabase($value);
                } else {
                    $data[$prop] = $value;
                }
            }
        }

        return $data;
    }

    public function save($recursive = false)
    {
        $data = $this->attributes();

        // Run before save
        $fields = static::getMetaData();
        foreach ($fields as $name => $field) {
            foreach ($field['attributes'] as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof BeforeSave) {
                    $instance->beforeSave($data[$name], $this);
                }
            }
        }

        if (empty($data[static::primaryKey()])) {
            db()->insert(static::table(), $data);
            $this->{static::primaryKey()} = db()->conn()->lastInsertId();
        } else {
            db()->update(static::table(), $data)
                ->where(static::primaryKey(), $data[static::primaryKey()])
                ->execute();
        }

        if ($recursive) {
            foreach ($this->relations as $key => $relation) {
                if (!is_array($relation)) {
                    $relation = [$relation];
                }
                foreach ($relation['records'] as $related) {
                    $self_key = $relation['relation']->self_key;
                    $foreign_key = $relation['relation']->foreign_key;

                    $related->$foreign_key = $this->$self_key;
                    $related->save(true);
                }
            }
        }
    }

    protected static function columns()
    {
        $meta = static::getMetaData();
        $columns = [];

        foreach ($meta as $prop => $info) {
            $columns[] = $prop;
        }

        return $columns;
    }

    /**
     * Relationships
     */
    public function belongsTo($model, $self_key = null, $foreign_key = null)
    {
        if (empty($foreign_key)) {
            $foreign_key = $model::primaryKey();
        }
        if (empty($self_key)) {
            $self_key = $model::table() . '_id';
        }

        return new Relation(
            $this,
            $model,
            false,
            $self_key,
            $foreign_key
        );
    }

    public function hasOne($model, $foreign_key = null, $self_key = null)
    {
        if (empty($self_key)) {
            $self_key = $this->primaryKey();
        }
        if (empty($foreign_key)) {
            $foreign_key = $this->table() . '_id';
        }

        return new Relation(
            $this,
            $model,
            false,
            $self_key,
            $foreign_key
        );
    }

    public function hasMany($model, $foreign_key = null, $self_key = null)
    {
        if (empty($self_key)) {
            $self_key = $this->primaryKey();
        }
        if (empty($foreign_key)) {
            $foreign_key = substr($this->table(), 0, -1) . '_id';
        }

        return new Relation(
            $this,
            $model,
            true,
            $self_key,
            $foreign_key
        );
    }
}
