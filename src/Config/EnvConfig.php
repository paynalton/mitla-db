<?php

namespace Paynalton\Mitladb\Config;

/**
 * Class EnvConfig
 *
 * Handles environment configuration, allowing retrieval of environment variables
 * and default values from an INI file.
 */
class EnvConfig{
    /**
     * @var array $_defaults Array of default values loaded from an INI file.
     */
    protected $_defaults=[];

    /**
     * EnvConfig constructor.
     *
     * @param string|null $defaultsFile Path to the INI file with default values (optional).
     */
    function __construct($defaultsFile=null)
    {
        if($defaultsFile){
            $this->_defaults=parse_ini_file($defaultsFile);
        }
    }

    /**
     * Retrieves the value of an environment variable.
     * If not set, returns the default value from the INI file (if available).
     *
     * @param string $name Name of the environment variable.
     * @return mixed|null Value of the variable or null if not set.
     */
    function __get($name)
    {
        $val=getenv($name);
        if($val === false){
            if(array_key_exists($name,$this->_defaults)){
                $val=$this->_defaults[$name];
            }
        }
        return $val;
    }
}