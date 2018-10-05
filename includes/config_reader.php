<?php

/**
 * This class read the config.ini file in the root directory.
 */
class config_reader
{
    /**
     * Separator for nesting levels of configuration data identifiers.
     *
     * @var string
     */
    protected $nestSeparator = '.';

    /**
     * Directory of the file to process.
     *
     * @var string
     */
    protected $directory;
    
    /**
     * Array to store all config
     *
     * @var array
     */
    protected $configData;
    
    /**
     * @var Singleton The reference to *Singleton* instance of this class
     */
    private static $instance;
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
            static::$instance->configData = static::$instance->fromFile('./config.ini');
        }
        
        return static::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
        
    }
    
    /**
     * This function will return config value for provided key
     * 
     * @param string $key
     * @return array
     */
    public function getKey($key = NULL)
    {
        if ($key == NULL) {
            return $this->configData;
        } else {
            if (!empty($this->configData[$key])) {
                return $this->configData[$key];
            }
            return '';
        }
    }
    
    /**
     * Set nest separator.
     *
     * @param  string $separator
     * @return self
     */
    public function setNestSeparator($separator)
    {
        $this->nestSeparator = $separator;
        return $this;
    }

    /**
     * Get nest separator.
     *
     * @return string
     */
    public function getNestSeparator()
    {
        return $this->nestSeparator;
    }

    /**
     * fromFile(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromFile()
     * @param  string $filename
     * @return array
     * @throws Exception
     */
    public function fromFile($filename)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new \Exception(sprintf(
                "File '%s' doesn't exist or not readable",
                $filename
            ));
        }

        $this->directory = dirname($filename);

        set_error_handler(
            function ($error, $message = '') use ($filename) {
                throw new \Exception(
                    sprintf('Error reading INI file "%s": %s', $filename, $message),
                    $error
                );
            },
            E_WARNING
        );
        $ini = parse_ini_file($filename, true);
        restore_error_handler();

        return $this->process($ini);
    }

    /**
     * fromString(): defined by Reader interface.
     *
     * @param  string $string
     * @return array|bool
     * @throws Exception
     */
    public function fromString($string)
    {
        if (empty($string)) {
            return [];
        }
        $this->directory = null;

        set_error_handler(
            function ($error, $message = '') {
                throw new \Exception(
                    sprintf('Error reading INI string: %s', $message),
                    $error
                );
            },
            E_WARNING
        );
        $ini = parse_ini_string($string, true);
        restore_error_handler();

        return $this->process($ini);
    }

    /**
     * Process data from the parsed ini file.
     *
     * @param  array $data
     * @return array
     */
    protected function process(array $data)
    {
        $config = [];

        foreach ($data as $section => $value) {
            if (is_array($value)) {
                if (strpos($section, $this->nestSeparator) !== false) {
                    $sections = explode($this->nestSeparator, $section);
                    $config = array_merge_recursive($config, $this->buildNestedSection($sections, $value));
                } else {
                    $config[$section] = $this->processSection($value);
                }
            } else {
                $this->processKey($section, $value, $config);
            }
        }

        return $config;
    }

    /**
     * Process a nested section
     *
     * @param array $sections
     * @param mixed $value
     * @return array
     */
    private function buildNestedSection($sections, $value)
    {
        if (count($sections) == 0) {
            return $this->processSection($value);
        }

        $nestedSection = [];

        $first = array_shift($sections);
        $nestedSection[$first] = $this->buildNestedSection($sections, $value);

        return $nestedSection;
    }

    /**
     * Process a section.
     *
     * @param  array $section
     * @return array
     */
    protected function processSection(array $section)
    {
        $config = [];

        foreach ($section as $key => $value) {
            $this->processKey($key, $value, $config);
        }

        return $config;
    }

    /**
     * Process a key.
     *
     * @param  string $key
     * @param  string $value
     * @param  array  $config
     * @return array
     * @throws Exception
     */
    protected function processKey($key, $value, array &$config)
    {
        if (strpos($key, $this->nestSeparator) !== false) {
            $pieces = explode($this->nestSeparator, $key, 2);

            if (!strlen($pieces[0]) || !strlen($pieces[1])) {
                throw new \Exception(sprintf('Invalid key "%s"', $key));
            } elseif (!isset($config[$pieces[0]])) {
                if ($pieces[0] === '0' && !empty($config)) {
                    $config = [$pieces[0] => $config];
                } else {
                    $config[$pieces[0]] = [];
                }
            } elseif (!is_array($config[$pieces[0]])) {
                throw new \Exception(
                    sprintf('Cannot create sub-key for "%s", as key already exists', $pieces[0])
                );
            }

            $this->processKey($pieces[1], $value, $config[$pieces[0]]);
        } else {
            if ($key === '@include') {
                if ($this->directory === null) {
                    throw new \Exception('Cannot process @include statement for a string config');
                }

                $reader  = clone $this;
                $include = $reader->fromFile($this->directory . '/' . $value);
                $config  = array_replace_recursive($config, $include);
            } else {
                $config[$key] = $value;
            }
        }
    }
}

