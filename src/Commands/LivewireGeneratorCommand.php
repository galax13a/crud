<?php

namespace Flightsadmin\LivewireCrud\Commands;

use Flightsadmin\LivewireCrud\ModelGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class GeneratorCommand.
 */
abstract class LivewireGeneratorCommand extends Command
{
    /**
     * The filesystem instance.
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Do not make these columns fillable in Model or views.
     * @var array
     */
    protected $unwantedColumns = [
        'id',
        'password',
        'email_verified_at',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $ruta; // Agregar la propiedad $ruta

    /**
     * Table name from argument.
     * @var string
     */
    protected $table = null;

    /**
     * Formatted Class name from Table.
     * @var string
     */
    protected $name = null;

    /**
     * Store the DB table columns.
     * @var array
     */
    private $tableColumns = null;

    /**
     * Model Namespace.
     * @var string
     */
    protected $modelNamespace = 'App\Models';

    /**
     * table Namespace.
     * @var string
     */
    protected $tableNamespace = 'App\Http\Livewire\Tables';
    /**
     * table Namespace.
     * @var string
     */
    protected $exportTableNamespace = 'App\Exports';

    /**
     * Controller Namespace.
     * @var string
     */
    protected $controllerNamespace = 'App\Http\Controllers';
    /**
     * Controller Namespace.
     * @var string
     */
    protected $livewireNamespace = 'App\Http\Livewire';

    /**
     * Application Layout
     * @var string
     */
    protected $layout = 'layouts.temas.starcho.admin.app';

    /**
     * Custom Options name
     * @var array
     */
    protected $options = [];

    /**
     * Create a new controller creator command instance.
     * @param \Illuminate\Filesystem\Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
        $this->unwantedColumns = config('livewire-crud.model.unwantedColumns', $this->unwantedColumns);
        $this->modelNamespace = config('crud.model.namespace', $this->modelNamespace);
        $this->controllerNamespace = config('livewire-crud.controller.namespace', $this->controllerNamespace);
        $this->livewireNamespace = config('livewire-crud.livewire.namespace', $this->livewireNamespace);
        $this->layout = config('livewire-crud.layout', $this->layout);
    }

    /**
     * Generate the Model.
     * @return $this
     */
    abstract protected function buildModel();

    /**
     * Generate the views.
     * @return $this
     */
    abstract protected function buildViews();

    /**
     * Build the directory if necessary.
     * @param string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }

        return $path;
    }

    /**
     * Write the file/Class.
     * @param $path
     * @param $content
     */
    protected function write($path, $content)
    {
        $this->files->put($path, $content);
    }

    /**
     * Get the stub file.
     * @param string $type
     * @param boolean $content
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function getStub($type, $content = true)
    {
        $stub_path = config('livewire-crud.stub_path', 'default');
        if ($stub_path == 'default') {
            $stub_path = __DIR__ . '/../stubs/';
        }

        $path = Str::finish($stub_path, '/') . "{$type}.stub";

        if (!$content) {
            return $path;
        }

        return $this->files->get($path);
    }

    /**
     * @param $no
     * @return string
     */
    private function _getSpace($no = 1)
    {
        $tabs = '';
        for ($i = 0; $i < $no; $i++) {
            $tabs .= "\t";
        }

        return $tabs;
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getMigrationPath($name)
    {
        return base_path("database/migrations/" . date('Y-m-d_His') . "_create_" . Str::lower(Str::plural($name)) . "_table.php");
    }
    protected function _getFactoryPath($name)
    {
        return base_path("database/factories/{$name}Factory.php");
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getLivewirePath($name)
    {
        return app_path($this->_getNamespacePath($this->livewireNamespace) . "{$name}s.php");
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getModelPath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePath($this->modelNamespace) . "{$name}.php"));
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getTablePath($name, $layot = 'admin')
    {
        $ruta = $this->tableNamespace . '\\' . $layot;
        return $this->makeDirectory(app_path($this->_getNamespacePath($ruta) . "{$name}sTable.php"));
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getTableExportPath($name, $layot = 'admin')
    {
        $ruta = $this->exportTableNamespace . '\\' . $layot;
        return $this->makeDirectory(app_path($this->_getNamespacePath($ruta) . "{$name}sExport.php"));
    }

    /**
     * Get the path from namespace.
     * @param $namespace
     * @return string
     */
    private function _getNamespacePath($namespace)
    {
        $str = Str::start(Str::finish(Str::after($namespace, 'App'), '\\'), '\\');

        return str_replace('\\', '/', $str);
    }

    /**
     * Get the default layout path.
     * @return string
     */
    private function _getLayoutPath()
    {
        return $this->makeDirectory(resource_path("/views/layouts/app.blade.php"));
    }

    /**
     * @param $view
     * @return string
     */
    protected function _getViewPath($view)
    {
        $name = Str::kebab($this->name);
        $ruta = $this->ruta ? Str::kebab($this->ruta) : $name; // Ruta predeterminada si no se proporciona una
        if (Str::contains($ruta, '.')) {
            $ruta = str_replace('.', '/', $ruta);
        }
        return $this->makeDirectory(resource_path("views/livewire/{$ruta}/{$view}.blade.php"));
        //return $this->makeDirectory(resource_path("views/livewire/{$ruta}/{$name}s/{$view}.blade.php"));
    }

    protected function generateColumnsTables() // generate  columns table.. starcho
    {
        $columns = [];
        foreach ($this->getFilteredColumns() as $column) {
            if ($column === 'active') {
                $columns[] = "Column::make('Active', 'active')->format(function (\$value, \$column, \$row) {
                    return view('components.starchocom.widgets.admin.table.active', ['active' => \$value]);
                })";
            } elseif ($column === 'enable') {
                $columns[] = "Column::make('Enable', 'enable')->format(function (\$value, \$column, \$row) {
                    return view('components.starchocom.widgets.admin.table.enable', ['enable' => \$value]);
                })";
            } elseif ($column === 'svg') {
                $columns[] = "Column::make('Svg', 'svg')->sortable()->searchable()->format(function (\$value, \$column, \$row) {
                    if (!\$value) {
                        return '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" class=\"bi bi-exclamation-circle\" viewBox=\"0 0 16 16\">
                            <path d=\"M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z\"/>
                            <path d=\"M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 0 1 1.8 0l-.35 3.602a.552.552 0 0 1-1.1 0L7.1 4.995z\"/>
                        </svg>';
                    }
                    \$base64Image = 'data:image/svg+xml;base64,' . base64_encode(\$value);
                    return '<img src=\"' . \$base64Image . '\" alt=\"SVG Image ' . \$column->name . '\" title=\"' . \$column->name . '\">';
                })->html()";
            } elseif (Str::endsWith($column, '_id') && $column !== 'user_id') {
                $relationName = Str::replaceLast('_id', '', $column);
                $columns[] = "Column::make('" . ucfirst(str_replace('_', ' ', $column)) . "', '$column')
                ->sortable()
                ->searchable()
                ->format(function (\$value, \$column, \$row){
                      return '<span>' . (\$column->{$relationName}?->name ?? 'N/A') . '</span>';
                })
                ->html()";
            } elseif ($column === 'icon') {
                $columns[] = "Column::make('Icon', 'icon')->sortable()->searchable()->format(function (\$value, \$column, \$row) {
                    return '<i title=\"' . \$column->name . '\" class=\"' . \$value . ' fs-3\"></i>';
                })->html()";
            } elseif (in_array($column, ['url', 'link', 'www', 'site'])) {
                $columns[] = "Column::make('" . ucfirst(str_replace('_', ' ', $column)) . "', '$column')->sortable()->searchable()->format(function (\$value, \$column, \$row) {
                    return '<a href=\"' . \$value . '\" target=\"_blank\" title=\"' . \$column->name . '\">' . \$value . '</a>';
                })->html()";
            } elseif ($column === 'site') {
                $columns[] = "Column::make('Site', 'site')->sortable()->searchable()->format(function (\$value, \$column, \$row) {
                    return '<a href=\"' . \$value . '\" target=\"_blank\" title=\"' . \$column->name . '\">' . \$value . '</a>';
                })->html()";
            } elseif ($column === 'target') {
                $columns[] = "Column::make('Target', 'target')->sortable()->searchable()->format(function (\$value, \$column, \$row) {
                    return '<span class=\"badge text-bg-warning\">' . \$value . '</span>';
                })->html()";
            } elseif (in_array($column, ['id', 'created_at', 'updated_at'])) {
                $columns[] = "Column::make('" . ucfirst(str_replace('_', ' ', $column)) . "', '$column')->sortable()";
            } else {
                $columns[] = "Column::make('" . ucfirst(str_replace('_', ' ', $column)) . "', '$column')->sortable()->searchable()";
            }
        }

        // Agrega la columna de acciones al final
        $columns[] = "Column::make('Actions', 'id')->format(function (\$value, \$column, \$row) {
            return view('livewire.starchocom.widgets.admin.table.btncrud', ['id' => \$value, 'name' => \$column->name]);
        })";

        return implode(",\n\t\t\t", $columns);
    }


    /**
     * Build the replacement.
     * @return array
     */
    protected function buildReplacements() // genera las variables del controladoe livewire ........ #live
    {

        //if()
        //{{modelNamePluralLowerCase}}' => {{modelName}}::latest()

        if ($this->hasUserRelation()) {  // si existe con user la relacion para mejorar el filtrado
            $head_model_render = "'" . Str::camel(Str::plural($this->name)) . "' => " . "{$this->name}::with('user')->latest()";
        } else {
            $head_model_render = "'" . Str::camel(Str::plural($this->name)) . "' => " . "{$this->name}::latest()";
        }

        $ruta_livewire = $this->ruta ? Str::kebab($this->ruta) : $this->name; // Ruta predeterminada si no se proporciona una

        $Namespace_TableExport = $this->exportTableNamespace . '\\' . $this->layout;
        $tableNamespace = $this->tableNamespace . '\\' . $this->layout;
        $table_NamespaceLivewire = $this->layout . '.' . strtolower($this->name) . 's-table';


        $nombre = $this->name;
        if (Str::endsWith($nombre, 's')) {
            $pluralNombre = $nombre;
        } else {
            $pluralNombre = Str::plural($nombre);
        }

        if (Str::endsWith($pluralNombre, 's')) {
            $pluralNombre = $nombre;
        } else {
            $pluralNombre = Str::plural($nombre);
        }

        if ($this->layout === 'app') $app = 'temas.starcho.admin.app';
        else $app = $this->layout;

     
        return [
            '{{layot_admin}}' => $this->layout,
            '{{layout}}' => $app,
            '{{modelName}}' => $pluralNombre, //$this->name,
            '{{modeloName}}' => strtolower($this->table),
            '{{modelTitle}}' => Str::title(Str::snake($this->name, ' ')),
            '{{modelNamespace}}' => $this->modelNamespace,
            '{{Namespace_TableExport}}' => $Namespace_TableExport,
            '{{tableNamespaceLivewire}}' => strtolower($table_NamespaceLivewire),
            '{{tableNamespace}}' => $tableNamespace,
            '{{controllerNamespace}}' => $this->controllerNamespace,
            '{{modelNamePluralLowerCase}}' => Str::camel(Str::plural($ruta_livewire)),
            '{{componentelivewire}}' => Str::camel($pluralNombre), //Str::camel(Str::plural($this->name)),            
            '{{modelNamePluralUpperCase}}' => ucfirst(Str::plural($this->name)),
            '{{headRender}}' => $head_model_render,
            '{{modelNameLowerCase}}' => Str::camel($this->name),
            '{{modelRoute}}' => $this->options['route'] ?? Str::kebab(Str::plural($this->name)),
            '{{modelView}}' => Str::kebab($this->name),
            '{{columns}}' => $this->generateColumnsTables(),
            '{{builder}}' => $this->builderTable(),
        ];
    }

    protected function builderTable()
    {
        // Obtén las columnas del modelo
        $columns = $this->getColumns();

        // Inicia el query del modelo con el nombre del modelo correcto
        $query = "return {$this->name}::query()";

        // Bandera para saber si existe una columna user_id
        $hasUserId = false;

        // Recorre las columnas y agrega relaciones dinámicas para las que terminan en '_id'
        foreach ($columns as $column) {
            if (Str::endsWith($column->Field, '_id')) {
                // Si la columna es 'user_id', marca la bandera
                if ($column->Field === 'user_id') {
                    $hasUserId = true;
                } else {
                    // Para otras columnas con '_id', se asume una relación belongsTo
                    $relation = Str::camel(str_replace('_id', '', $column->Field));
                    $query .= "->with('{$relation}')";
                }
            }
        }

        // Si existe la columna 'user_id', agregar la condición where
        if ($hasUserId) {
            $query .= "->where('user_id', auth()->user()->id)";
        }

        // Terminar el query y agregar el método builder con el nombre de modelo correcto
        return "public function builder(): Builder\n{\n    {$query};\n}\n";
    }


    /**
     * Build the form fields for form.
     * @param $title
     * @param $column
     * @param string $type
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */

    protected function getField($title, $column, $type = 'form-field', $table = 'null')
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
            '{{column}}' => $column,
        ]);

        if (Str::endsWith($column, '_id')) {
            $relation = $this->Starcho_RelationTable($column);

            if ($relation['type'] === 'belongsTo' || $relation['type'] === 'potentialBelongsTo') {
                if ($relation['related_table'] !== $this->table) {
                    $table = $relation['related_table'];

                    $replace_select_table = array_merge($this->buildReplacements(), [
                        '{{table}}' => $table,
                        '{{column}}' => $column,
                    ]);

                    return str_replace(
                        array_keys($replace_select_table),
                        array_values($replace_select_table),
                        $this->getStub("views/{$type}-select-table")
                    );
                }
            }
        } elseif ($this->isEnumColumn($column)) {
            $enumOptions = $this->getEnumOptions($this->table, $column);
            $replace['{{options}}'] = implode(',', $enumOptions);

            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$type}-enum-table")
            );
        } elseif ($column == 'date' || $column == 'birthday') {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$type}-date")
            );
        } elseif ($column == 'active') {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$type}-active")
            );
        } elseif ($column == 'enable') {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$type}-enable")
            );
        } else {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$type}")
            );
        }
    }

    protected function Starcho_RelationTable($column)
    {
        $db = DB::getDatabaseName();
        $table = $this->table;

        $sql = "
        SELECT REFERENCED_TABLE_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '$db' 
          AND TABLE_NAME = '$table' 
          AND COLUMN_NAME = '$column' 
          AND REFERENCED_TABLE_NAME IS NOT NULL";

        $result = DB::select($sql);

        if (!empty($result)) {
            return [
                'type' => 'belongsTo',
                'related_table' => $result[0]->REFERENCED_TABLE_NAME
            ];
        }

        // Si la columna termina en '_id' pero no tiene una relación definida
        if (Str::endsWith($column, '_id')) {
            $potentialTable = Str::plural(Str::replaceLast('_id', '', $column));
            return [
                'type' => 'potentialBelongsTo',
                'related_table' => $potentialTable
            ];
        }

        return [
            'type' => null,
            'related_table' => null
        ];
    }

    protected function isEnumColumn($column)
    {
        $type = DB::select("SHOW COLUMNS FROM `{$this->table}` WHERE Field = '{$column}'")[0]->Type;
        return Str::startsWith($type, 'enum');
    }

    protected function getEnumOptions($table, $column)
    {
        $type = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = '{$column}'")[0]->Type;
        preg_match('/^enum\((.*)\)$/', $type, $matches);
        $enumValues = str_getcsv($matches[1], ',', "'");
        return $enumValues;
    }


    /**
     * @param $title
     * @return mixed
     */
    protected function getHead($title) // generr titulos de las tables column
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->_getSpace(4) . '<th>{{title}}</th>' . "\n"
        );
    }

    /**
     * @param $column
     * @return mixed
     */
    protected function getBody($column) // generacion de la columnas table
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{column}}' => $column,
        ]);

        if (Str::endsWith($column, 'name')) {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->_getSpace(4) . '<td data-record="{{ $row->id }}">{{ $row->name }}</td>' . "\n"
            );
        }

        if ($column == 'active') {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->_getSpace(4) . '<td class="text-center"><x-com-active :active="$row->active" /></td>' . "\n"
            );
        } elseif (Str::endsWith($column, '_id')) {
            // quita "_id" al final y agrega "->name"
            $relationColumn = Str::replaceLast('_id', '', $column) . '->name';
            $replace['{{column}}'] = $relationColumn;

            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->_getSpace(4) . '<td>{{ $row->{{column}} }}</td>' . "\n"
            );
        } else {
            return str_replace(
                array_keys($replace),
                array_values($replace),
                $this->_getSpace(4) . '<td>{{ $row->{{column}} }}</td>' . "\n"
            );
        }
    }

    /**
     * Make layout if not exists.
     * @throws \Exception
     */
    protected function buildLayout(): void
    {
        if (!(view()->exists($this->layout))) {

            $this->info('Creating Layout ...');

            if ($this->layout == 'layouts.app') {
                $this->files->copy($this->getStub('layouts/app', false), $this->_getLayoutPath());
            } else {
                throw new \Exception("{$this->layout} layout not found!");
            }
        }
    }

    /**
     * Get the DB Table columns.
     * @return array
     */
    protected function getColumns()
    {
        if (empty($this->tableColumns)) {
            $this->tableColumns = DB::select('SHOW COLUMNS FROM ' . $this->table);
        }

        return $this->tableColumns;
    }

    protected function getModelUser()
    {
        if (empty($this->tableColumns)) {
            $this->tableColumns = DB::select('SHOW COLUMNS FROM ' . $this->table);
        }

        // Verificar si existe la columna "user_id"
        foreach ($this->tableColumns as $column) {
            if ($column->Field === 'user_id') {
                return true;
            }
        }

        // Si no se encontró la columna "user_id", devolver false
        return false;
    }

    /**
     * @return array
     */
    protected function getFilteredColumns()
    {
        $unwanted = $this->unwantedColumns;
        if ($this->hasUserRelation()) {
            $unwanted[] = 'user_id';
        }

        $columns = [];

        foreach ($this->getColumns() as $column) {
            $columns[] = $column->Field;
        }

        return array_filter($columns, function ($value) use ($unwanted) {
            return !in_array($value, $unwanted);
        });
    }

    /**
     * Make model attributes/replacements.
     * @return array
     */

    protected function hasUserRelation() // relacion con la tabla users comprueba
    {
        // Comprueba si existe la relación con la tabla de usuarios
        $columns = Schema::getColumnListing($this->table);

        return in_array('user_id', $columns);
    }


    protected function generateBootedCode() // user Boot
    {
        if ($this->hasUserRelation()) {
            return "
    protected static function booted() // Starcho booted
    {
        // Apply global scope to filter by authenticated user
        static::addGlobalScope('user_id', function (Builder \$builder) {
            if (auth()->check()) {
                \$builder->where('user_id', auth()->id());
            } else {
                abort(500, 'Unauthorized action. edit unban');
            }
        });

        static::creating(function (\$model) {
            if (auth()->check()) {
                \$model->user_id = auth()->id();
            }
        });

        static::updating(function (\$model) {
            if (auth()->check()) {
                if (\$model->user_id != auth()->id()) {
                    abort(500, 'Unauthorized action.');
                }
            }
        });

        static::deleting(function (\$model) {
            if (auth()->check()) {
                if (\$model->user_id != auth()->id()) {
                    return response()->json([
                        'error' => 'Unauthorized action. Delete Uban'
                    ], 500);
                }
            }
        });
    }";
        } else {
            return "
    protected static function booted() // Starcho booted
    {
        // No user relation, no additional booted logic required
    }";
        }
    }

    protected function modelReplacements() // generador de modelo Full ***********445 ******
    {
        $properties = '';
        $rulesArray = [];
        $softDeletesNamespace = $softDeletes = '';

        foreach ($this->getColumns() as $value) {
            $properties .= "\n * @property $$value->Field";

            if ($value->Null == 'NO') {
                $rulesArray[$value->Field] = 'required';
            }

            if ($value->Field == 'deleted_at') {
                $softDeletesNamespace = "use Illuminate\Database\Eloquent\SoftDeletes;\n";
                $softDeletes = "use SoftDeletes;\n";
            }
        }

        $rules = function () use ($rulesArray) {
            $rules = '';
            // Exclude the unwanted rulesArray
            $rulesArray = Arr::except($rulesArray, $this->unwantedColumns);
            // Make rulesArray
            foreach ($rulesArray as $col => $rule) {
                if ($col === 'user_id') {
                    // Skip adding the rule for 'user_id'
                    continue;
                } elseif ($col === 'url') {
                    // Add special rule for 'url'
                    $rules .= "\n\t\t'{$col}' => '{$rule}|url',";
                } elseif ($col === 'api') {
                    // Add special rule for 'url'
                    $rules .= "\n\t\t'{$col}' => '{$rule}|url',";
                } elseif ($col === 'email') {
                    // Add special rule for 'email'
                    $rules .= "\n\t\t'{$col}' => '{$rule}|email',";
                } else {
                    $rules .= "\n\t\t'{$col}' => '{$rule}',";
                }
            }

            return $rules;
        };


        $fillable = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "'" . $value . "'";
            });

            // CSV format
            return implode(',', $filterColumns);
        };

        $updatefield = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "$" . $value . "";
            });

            // CSV format
            return implode(', ', $filterColumns);
        };

        $resetfields = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "\n\t\t\$this->" . $value . " = null";
                $value .= ";";
            });

            // CSV format
            return implode('', $filterColumns);
        };

        $addfields = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "\n\t\t\t'" . $value . "' => \$this-> " . $value;
            });

            // CSV format
            return implode(',', $filterColumns);
        };

        $keyWord = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();
            // Add quotes to the unwanted columns for fillable // render controlador de livewire                   
            if ($this->hasUserRelation()) {

                return "\n
                ->where('user_id', auth()->id())
                ->where(function (\$query) use (\$keyWord) {     
                    \$query->where('name', 'LIKE', \$keyWord)        
                    ->orWhere('name', 'LIKE', \$keyWord); 
                })";
            } else {
                array_walk($filterColumns, function (&$value) {
                    $value = "\n\t\t\t\t\t\t->orWhere('" . $value . "', 'LIKE', \$keyWord)";
                });
            }



            // CSV format
            return implode('', $filterColumns);
        };

        $factoryfields = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable */
            array_walk($filterColumns, function (&$value) {
                $value = "\n\t\t\t'" . $value . "' => \$this->faker->name,";
            });

            // CSV format
            return implode('', $filterColumns);
        };

        $editfields = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "\n\t\t\t\$this->" . $value . " = \$record-> " . $value . ";";
            });

            // CSV format
            return implode('', $filterColumns);
        };

        list($relations, $properties) = (new ModelGenerator($this->table, $properties, $this->modelNamespace))->getEloquentRelations();

        return [
            '{{fillable}}' => $fillable(),
            '{{updatefield}}' => $updatefield(),
            '{{resetfields}}' => $resetfields(),
            '{{editfields}}' => $editfields(),
            '{{addfields}}' => $addfields(),
            '{{factory}}' => $factoryfields(),
            '{{rules}}' => $rules(),
            '{{search}}' => $keyWord(),
            '{{relations}}' => $relations,
            '{{booted_users}}' => $this->generateBootedCode(),
            '{{properties}}' => $properties,
            '{{softDeletesNamespace}}' => $softDeletesNamespace,
            '{{softDeletes}}' => $softDeletes,
        ];
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the table'],
        ];
    }

    /**
     * Is Table exist in DB.
     * @return mixed
     */
    protected function tableExists()
    {
        return Schema::hasTable($this->table);
    }
}
