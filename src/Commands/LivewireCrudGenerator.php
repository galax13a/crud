<?php

namespace Flightsadmin\LivewireCrud\Commands;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class LivewireCrudGenerator extends LivewireGeneratorCommand
{
	
	protected $filesystem;
    protected $stubDir;
    protected $argument;
    private $replaces = [];

    protected $signature = 'crud:generate {name : Table name} {layout=app : Layout name} {ruta=admin : router name}';
    protected $layout, $ruta;
    protected $description = 'Generate Livewire Component and CRUD operations';

    public function handle()
{
    $this->table = $this->getNameInput();
    $layout = $this->argument('layout') ?: 'app';
    $this->layout = $layout;
    $ruta = $this->argument('ruta');
    //$ruta = $this->ruta ? $this->argument('ruta'): $this->table; 
    if (!$ruta) {
        // Si no se proporciona una ruta, usa el nombre de la tabla como ruta
        $ruta = Str::slug($this->table, '-');
    }

    $this->ruta = $ruta;

    // Si la tabla no existe en la base de datos, muestra un error y devuelve false
    if (!$this->tableExists()) {
        $this->error("`{$this->table}` table does not exist");

        return false;
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
    $routeFile = base_path('routes/web.php');
    $routeContents = $this->filesystem->get($routeFile);
    $routeItemStub = "\tRoute::view('" . $ruta2 . "', 'livewire." . $ruta . ".index')->middleware('auth');";
    $routeItemHook = '//Route Hooks - Do not delete//';

    if (!Str::contains($routeContents, $routeItemStub)) {
        $newContents = str_replace($routeItemHook, $routeItemHook . PHP_EOL . $routeItemStub, $routeContents);
        $this->filesystem->put($routeFile, $newContents);
        $this->warn('Route inserted: <info>' . $routeFile . '</info>');
    }

    // Actualiza la barra de navegación
    $layoutFile = 'resources/views/layouts/app.blade.php';
    $layoutContents = $this->filesystem->get($layoutFile);
  
    // Ajusta la ruta para que coincida con la estructura de carpetas
    if (Str::contains($ruta, '.')) {
        $ruta = str_replace('.', '/', $ruta);
    }


    $navItemStub = "\t\t\t\t\t\t<li class=\"nav-item\">
        <a href=\"{{ url('/" . $ruta . "') }}\" class=\"nav-link\">🟣 " . ucfirst($this->table) . "</a>
    </li>";
    $navItemHook = '<!--Nav Bar Hooks - Do not delete!!-->';

    if (!Str::contains($layoutContents, $navItemStub)) {
        $newContents = str_replace($navItemHook, $navItemHook . PHP_EOL . $navItemStub, $layoutContents);
        $this->filesystem->put($layoutFile, $newContents);
        $this->warn('Nav link inserted: <info>' . $layoutFile . '</info>');
    }

    $this->info('');
    $this->info('Livewire Component & CRUD Generated Successfully.');

    return true;
}

    protected function buildModel()
    {
        $modelPath = $this->_getModelPath($this->name);
		$livewirePath = $this->_getLivewirePath($this->name);
        $factoryPath = $this->_getFactoryPath($this->name);

        $Modeluser = $this->getModelUser();

        if ($this->files->exists($livewirePath) && $this->ask("Livewire Component ". Str::studly(Str::singular($this->table)) ."Component Already exist. Do you want overwrite (y/n)?", 'y') == 'n') {
            return $this;
        }

        // Make Replacements in Model / Livewire / Migrations / Factories modic cr26
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

        if ($Modeluser) {
            // La columna "user_id" existe
            $modelTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub('ModelUser')
            );
            $livewireTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub('LivewireUser')
            );
        } else {
            // La columna "user_id" no existe
            $modelTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub('Model')
            );
            $livewireTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub('Livewire')
            );
        }

		$factoryTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('Factory')
        );
      
        $this->warn('Creating: <info>Livewire Component...</info>');
        $this->write($livewirePath, $livewireTemplate);
		$this->warn('Creating: <info>Model...</info>');
        $this->write($modelPath, $modelTemplate);
        $this->warn('Creating: <info>Factories, Please edit before running Factory ...</info>');
        $this->write($factoryPath, $factoryTemplate);

        return $this;
    }

    protected function buildViews()
{
    $this->warn('Creating:<info> Views CRv1.0 Layout :  ' .  $this->layout .' ...</info>');

    $tableHead = "\n";
    $tableBody = "\n";
    $viewRows = "\n";
    $form = "\n";
    $type = null;

    foreach ($this->getFilteredColumns() as $column) {
        $title = Str::title(str_replace('_', ' ', $column));

        $tableHead .= "\t\t\t\t". $this->getHead($title);
        $tableBody .= "\t\t\t\t". $this->getBody($column);
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
            array_keys($replace), array_values($replace), $this->getStub("views/{$view}")
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