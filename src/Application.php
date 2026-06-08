<?php

namespace framework;

use Exception;
use framework\contracts\ApplicationInterface;
use framework\contracts\ComponentInterface;
use framework\contracts\ExtensionInterface;
use framework\models\Model;
use framework\models\transformers\DateTimeTransformer;
use Override;

/**
 * Base application class
 * 
 * @property-read \framework\components\Config $config
 * @property-read \framework\components\PathManager $path
 * @property-read \framework\components\Logger $logger
 * @property-read \framework\components\Validator $validator
 */
abstract class Application implements ApplicationInterface
{
    protected static ?Application $instance = null;

    protected array $initialized = [];

    /**
     * Container for registered components
     * @var array<string, ComponentInterface|string|callable>
     */
    protected array $components = [];

    /**
     * List of installed extensions
     * @var array<string, ExtensionInterface>
     */
    protected array $extensions = [];

    public function init()
    {
        Model::registerTypeTransformer('DateTime', new DateTimeTransformer());

        foreach ($this->components as $key => $component) {
            if ($component instanceof ComponentInterface) {
                $component->init();
                $this->initialized[$key] = true;
            }
        }

        foreach ($this->extensions as $extension) {
            $extension->init();
            $extension->bootstrap($this);
        }
    }

    public abstract function run();

    /**
     * Short alias for getInstance()
     */
    public static function get(): Application
    {
        return static::$instance;
    }

    /**
     * Register a component in the container
     */
    public function registerComponent(string $name, $component): void
    {
        $this->components[$name] = $component;
    }

    /**
     * Magic getter for components
     */
    public function __get(string $name)
    {
        $com = $this->components[$name];

        if (is_string($com)) {
            $this->components[$name] = $this->di->make($com);

            $this->components[$name]->init();
            $this->initialized[$name] = true;
        } else if (is_callable($com)) {
            $this->components[$name] = $com($this);
            $this->components[$name]->init();
            $this->initialized[$name] = true;
        } else if ($this->components[$name] instanceof ComponentInterface && !isset($this->initialized[$name])) {
            $this->components[$name]->init();
            $this->initialized[$name] = true;
        }

        return $this->components[$name] ?? null;
    }

    /**
     * Check if component exists
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->components);
    }

    public function scanModules()
    {
        $vendorDir = config('paths.vendor');
        
        if (!is_dir($vendorDir)) {
            return;
        }

        // Scan vendor directory for packages
        $vendors = array_filter(glob($vendorDir . '/*'), 'is_dir');
        
        foreach ($vendors as $vendorPath) {
            // Skip special directories
            if (basename($vendorPath) === 'composer' || basename($vendorPath) === 'bin') {
                continue;
            }
            
            $packages = array_filter(glob($vendorPath . '/*'), 'is_dir');
            
            foreach ($packages as $packagePath) {
                $composerFile = $packagePath . '/composer.json';
                
                if (!file_exists($composerFile)) {
                    continue;
                }
                
                $composerData = json_decode(file_get_contents($composerFile), true);
                
                if (!$composerData || !isset($composerData['extra']['bolt']['providers'])) {
                    continue;
                }
                
                $providers = $composerData['extra']['bolt']['providers'];
                
                if (!is_array($providers)) {
                    $providers = [$providers];
                }
                
                foreach ($providers as $providerClass) {
                    if (!class_exists($providerClass)) {
                        continue;
                    }
                    
                    $provider = new $providerClass();
                        
                    if (method_exists($provider, 'boot')) {
                        $provider->boot($this);
                    }
                    else {
                        throw new Exception("Provider $providerClass does not have a boot method.");
                    }
                }
            }
        }
    }

    public function registerRoutes(string $dir): void {
        throw new Exception("Rotues must be registered within web / console");
    }

    #[Override]
    public function registerResources(string $namespace, string $dir): void
    {
        throw new \Exception('Resources must be registered within web / console');
    }
}