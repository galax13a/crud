<?php

namespace Flightsadmin\LivewireCrud;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModelGenerator
{
    private $functions = null;
    private $table = null;
    private $properties = null;
    private $modelNamespace = 'App\Models';

    public function __construct(string $table, string $properties, string $modelNamespace)
    {
        $this->table = $table;
        $this->properties = $properties;
        $this->modelNamespace = $modelNamespace;
        $this->_init();
    }

    public function getEloquentRelations()
    {
        return [$this->functions, $this->properties];
    }

    private function _init()
    {
        $relations = $this->_getTableRelations();
        
        foreach ($relations as $relation) {
            if ($relation->ref == '1') {
                // This table is referenced by another table
                $eloquent = 'hasMany';
                if ($this->_isOneToOne($relation->ref_table, $relation->foreign_key)) {
                    $eloquent = 'hasOne';
                }
                $this->functions .= $this->_getFunction($eloquent, $relation->ref_table, $relation->foreign_key, $relation->local_key);
            } else {
                // This table references another table
                $eloquent = 'belongsTo';
                $this->functions .= $this->_getFunction($eloquent, $relation->ref_table, $relation->local_key, $relation->foreign_key);
            }
        }

        // Add potential self-referencing relationship
        if ($this->_hasSelfReferencingColumn()) {
            $this->functions .= $this->_getSelfReferencingFunctions();
        }
    }

    private function _getFunction(string $relation, string $table, string $foreignKey, string $localKey)
    {
        list($model, $relationName) = $this->_getModelName($table, $relation);
        $relClass = ucfirst($relation);

        switch ($relation) {
            case 'hasOne':
            case 'belongsTo':
                $this->properties .= "\n * @property $model $$relationName";
                break;
            case 'hasMany':
                $this->properties .= "\n * @property ".$model."[] $$relationName";
                break;
        }

        return "
    /**
     * @return \Illuminate\Database\Eloquent\Relations\\$relClass
     */
    public function $relationName()
    {
        return \$this->$relation('{$this->modelNamespace}\\$model', '$foreignKey', '$localKey');
    }
    ";
    }

    private function _getModelName($name, $relation)
    {
        $class = Str::studly(Str::singular($name));
        $relationName = Str::camel($relation === 'belongsTo' ? Str::singular($name) : $name);
        return [$class, $relationName];
    }

    private function _getTableRelations()
    {
        $db = DB::getDatabaseName();
        $sql = "
            SELECT TABLE_NAME ref_table, COLUMN_NAME foreign_key, REFERENCED_COLUMN_NAME local_key, '1' ref 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE REFERENCED_TABLE_NAME = '$this->table' AND TABLE_SCHEMA = '$db'
            UNION
            SELECT REFERENCED_TABLE_NAME ref_table, REFERENCED_COLUMN_NAME foreign_key, COLUMN_NAME local_key, '0' ref 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = '$this->table' AND TABLE_SCHEMA = '$db' AND REFERENCED_TABLE_NAME IS NOT NULL 
            ORDER BY ref_table ASC
        ";
        return DB::select($sql);
    }

    private function _isOneToOne($table, $column)
    {
        $result = DB::select("SHOW KEYS FROM $table WHERE Column_name = ?", [$column]);
        return !empty($result) && ($result[0]->Key_name === 'PRIMARY' || $result[0]->Non_unique == 0);
    }

    private function _hasSelfReferencingColumn()
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($this->table);
        return in_array('parent_id', $columns);
    }

    private function _getSelfReferencingFunctions()
    {
        $modelName = Str::studly(Str::singular($this->table));
        return "
    public function parent()
    {
        return \$this->belongsTo('{$this->modelNamespace}\\$modelName', 'parent_id');
    }

    public function children()
    {
        return \$this->hasMany('{$this->modelNamespace}\\$modelName', 'parent_id');
    }
    ";
    }
}