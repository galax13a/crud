<?php

namespace Flightsadmin\LivewireCrud\Commands;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class LivewireCrudGenerator extends LivewireGeneratorCommand
{

    protected $filesystem;
    protected $stubDir;
    protected $argument;
    private $replaces = [];

    //protected $signature = 'crud:generate {name : Table name} {layout=app : Layout name} {ruta=admin : router name}';
    protected $signature = 'crud:generate';
    protected $layout, $ruta;
    protected $description = 'Generate Livewire Component and CRUD operations | Starcho V1';


    public function handle()
    {
        $this->info('Starcho CRUD v1 : You are about to generate a CRUD for:');
        $this->table = $this->ask('What is the table name?');

        if (!$this->table) {
            $this->error('Name Table Null.');
            return;
        }

        $this->layout = $this->ask('Which layout would you like to use? The main app is the admin layout is app or admin', 'admin');
        $this->ruta = $this->ask('What route would you like to use? '.$this->layout .'.'.$this->table.'', $this->layout . '.' . $this->table);
        $ruta =  $this->ruta;

        if (!$this->tableExists()) {
            $this->error("`{$this->table}` table does not exist");
            return false;
        }
        $answer = false;
        if ($answer) {
            if (!$this->confirm('Do you wish to continue?')) {
                $this->info('Command canceled.');
                return;
            }
        }

        if (!$this->ruta) {
            // Si no se proporciona una ruta, usa el nombre de la tabla como ruta
            $ruta = Str::slug($this->table, '-');
        }
        // Construye el nombre de clase a partir del nombre de la tabla
        $this->name = $this->_buildClassName();

        // Genera el CRUD
        $this->buildModel()
            ->buildViews();

        // Ajusta la ruta para que coincida con la estructura de carpetas
        if (Str::contains($ruta, '.')) {
            $ruta2 = str_replace('.', '/', $ruta);
        }

        // Actualiza las rutas
        $this->filesystem = new Filesystem;

        if ($this->layout === 'admin' || $this->layout === 'app') {
            $routeFile = base_path("routes/{$this->layout}.php");
        } else {
            $routeFile = base_path('routes/web.php');
            $this->layout = 'web'; // Aseguramos que $this->layout sea 'web' para cualquier otro valor
        }

        $routeContents = $this->filesystem->get($routeFile);

        // Ajusta la ruta para que coincida con la estructura de carpetas
        $viewPath = 'livewire.' . $ruta . '.index';
        $ruta_can = str_replace('/', '.', $ruta2);

        if ($this->layout === 'admin') {
            $routeItemStub = "\tRoute::view('" . $this->table . "', '" . $viewPath . "')" .
                "\n\t\t->middleware('can:" . $ruta_can . "')->name('" . $ruta_can . "');";
        } elseif ($this->layout === 'app') {
            $routeItemStub = "\tRoute::view('" . $this->table . "', '" . $viewPath . "')" .
                "\n\t\t->name('" . $ruta_can . "');";
        } else {
            $routeItemStub = "\tRoute::view('" . $ruta_can . "', '" . $viewPath . "')->middleware('auth');";
        }

        $routeItemHook = '//Route Hooks - Do not delete//';

        if (!Str::contains($routeContents, $routeItemStub)) {
            $newContents = str_replace($routeItemHook, $routeItemHook . PHP_EOL . $routeItemStub, $routeContents);
            $this->filesystem->put($routeFile, $newContents);
            $this->warn('Route inserted: <info>' . $routeFile . '</info>');
        }
        // Actualiza la barra de navegación
        if ($this->layout === 'app') {
            $layoutFile = 'resources/views/layouts/temas/starcho/admin/app.blade.php';
        }else {        
            $layoutFile = 'resources/views/layouts/admin.blade.php';
        }
        $layoutContents = $this->filesystem->get($layoutFile);
        $ruta_can = str_replace('/', '.', $ruta2);
        
        $ruta = str_replace('.', '/', $ruta);
        $navItemStub = "\t\t\t\t\t\t<li class=\"nav-item\">
                                <a href=\"{{ route('" . $ruta_can . "') }}\" class=\"nav-link\" target=\"_blank\" target=\"_blank\">🟣 " . ucfirst($this->table) . "</a>
                        </li>";       

        $navItemHook = '<!--Nav Bar Hooks - Do not delete!!-->';

        if (!Str::contains($layoutContents, $navItemStub)) {
            $newContents = str_replace($navItemHook, $navItemHook . PHP_EOL . $navItemStub, $layoutContents);
            $this->filesystem->put($layoutFile, $newContents);
            $this->warn('Nav link inserted: <info>' . $layoutFile . '</info>');
        }

        $permissionExists = DB::table('permissions')->where('name', $ruta_can)->exists();
        if (!$permissionExists) {
            // Si no existe, crea el permiso y asigna los roles
            Permission::firstOrCreate(['name' => $ruta_can, 'description' => 'Admin ->'.$ruta_can])->syncRoles(['root', 'admin']);
        }
        
        Artisan::call('cache:forget spatie.permission.cache');               
        Artisan::call('route:cache'); // reset route cache
        $this->info('');
        $this->info(' Starcho -> Livewire Component & CRUD Generated Successfully.');
        //finish crud
        return true;
    }

    protected function buildModel()
    {
        $modelPath = $this->_getModelPath($this->name);
        $livewirePath = $this->_getLivewirePath($this->name);
        $factoryPath = $this->_getFactoryPath($this->name);
        $tablePath = $this->_getTablePath($this->name, $this->layout);
        $exportTablePath = $this->_getTableExportPath($this->name, $this->layout);

        $Modeluser = $this->getModelUser();

        if ($this->files->exists($livewirePath) && $this->ask("Livewire Component " . Str::studly(Str::singular($this->table)) . "Component Already exist. Do you want overwrite (y/n)?", 'y') == 'n') {
            return $this;
        }

        // Make Replacements in Model / Livewire / Migrations / Factories modic cr26
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

        if ($Modeluser) {
            // La columna "user_id" existe
            $modelTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub('ModelUser')
            );
            $livewireTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub('LivewireUser')
            );
        } else {
            // La columna "user_id" no existe
            $modelTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub('Model')
            );
            $livewireTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub('Livewire')
            );
        }

        $factoryTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('Factory')
        );

        $TableTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('Table')
        );

        $TableExportTemplate = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub('TableExport')
        );

        $this->warn('Creating: <info>Livewire Component...</info>');
        $this->write($livewirePath, $livewireTemplate);
        $this->warn('Creating: <info>Model OK...</info>');
        $this->write($modelPath, $modelTemplate);
        $this->warn('Creating: <info>Factories, Please edit before running Factory ...</info>');
        $this->write($factoryPath, $factoryTemplate);

        $this->warn('Creating: <info>TableLiveWire, OK running Table ...</info>');
        $this->write($tablePath, $TableTemplate);

        $this->warn('Creating: <info>Export Table, OK ExportTable ...</info>');
        $this->write($exportTablePath, $TableExportTemplate);

        return $this;
    }

    protected function buildViews()
    {
        $this->warn('Creating:<info> Views Starcho 1.0 Layout :  ' .  $this->layout . ' ...</info>');

        $tableHead = "\n";
        $tableBody = "\n";
        $viewRows = "\n";
        $form = "\n";
        $type = null;

        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));

            $tableHead .= "\t\t\t\t" . $this->getHead($title);
            $tableBody .= "\t\t\t\t" . $this->getBody($column);
            $form .= $this->getField($title, $column, 'form-field');
            $form .= "\n";
        }

        foreach ($this->getColumns() as $values) {
            $type = "text";
        }

        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeader}}' => $tableHead,
            '{{tableBody}}' => $tableBody,
            '{{viewRows}}' => $viewRows,
            '{{form}}' => $form,
            '{{type}}' => $type,
        ]);

        foreach (['view', 'index', 'modals'] as $view) {
            $viewTemplate = str_replace(
                array_keys($replace),
                array_values($replace),
                $this->getStub("views/{$view}")
            );

            // Reemplaza '{{layout}}' con el valor de $layout en el archivo index.stub
            $viewTemplate = str_replace('{{layout}}', $this->layout, $viewTemplate);

            // Reemplaza el @extends en el archivo index.stub con el valor de $layout
            if ($view === 'index') {
                $viewTemplate = str_replace('@extends(\'layouts.\')', "@extends('layouts.' . \$layout)", $viewTemplate);
            }

            $this->write($this->_getViewPath($view), $viewTemplate);
        }

        return $this;
    }
    /**
     * Make the class name from table name.
     */
    private function _buildClassName()
    {
        return Str::studly(Str::singular($this->table));
    }

    private function replace($content)
    {
        foreach ($this->replaces as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }
}
