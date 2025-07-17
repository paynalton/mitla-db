<?php

namespace Paynalton\Mitladb\Migration;

use Paynalton\Mitladb\Config\EnvConfig;
use Illuminate\Database\Capsule\Manager as Base;

/**
 * Class ManagerMigration
 *
 * Manages database migrations, including applying, reverting, and tracking migration stages.
 * Extends the Illuminate\Database\Capsule\Manager for database operations.
 */
class ManagerMigration extends Base{

    /**
     * @var EnvConfig $_credentials Stores environment/database credentials.
     */
    protected $_credentials;

    /**
     * ManagerMigration constructor.
     *
     * @param EnvConfig|null $config Optional configuration object for credentials.
     */
    function __construct(EnvConfig $config = null)
    {
        if($config){
            $this->setCredentials($config);
        }
    }

    /**
     * Sets the credentials and initializes the database connection.
     *
     * @param EnvConfig $config Configuration object containing credentials.
     * @return void
     */
    public function setCredentials(EnvConfig $config){
        $this->_credentials=$config;
        $this->initCredentials();
    }

    /**
     * Applies migrations up to a specified stage.
     *
     * @param bool $fresh If true, drops all tables before migrating.
     * @param bool $seed If true, seeds after each migration stage.
     * @param string|null $stageTo Target migration stage.
     * @return void
     */
    public function up($fresh=false,$seed=false,$stageTo=null){
        if($fresh){
            $this->dropAll();
            $current=null;
            $this->getCurrentVersion();
        }else{
            $current=$this->getCurrentVersion();
        }
        $stages=$this->getStages($current,$stageTo);
        foreach($stages as $s){
            $s->apply();
            if($seed){
                $s->seed();
            }
            $this->setCurrentVersion($s->name);
        }
    }

    /**
     * Reverts migrations down to a specified stage.
     *
     * @param string|null $stageTo Target migration stage to revert to.
     * @return void
     */
    public function down($stageTo=null){
        $current=$this->getCurrentVersion();
        $stages=$this->getStages($stageTo,$current);
        $stages=array_reverse($stages);
        foreach($stages as $s){
            $s->undo();
        }
        $this->setCurrentVersion($stageTo);
    }

    /**
     * Drops all tables in the database except the migration status table.
     *
     * @return void
     */
    protected function dropAll(){
        $tables = self::select("SELECT
                table_name
            FROM
                information_schema.tables
            WHERE
                table_schema = '".$this->_credentials->DB_DATABASE."' and table_name<>'SO____migration_status';");
        foreach($tables as $k=>$t){
            $tables[$k]="DROP TABLE IF EXISTS `". ( property_exists( $t, "TABLE_NAME") ? $t->TABLE_NAME : $t->table_name)."`;";
        }
        array_unshift($tables,"SET FOREIGN_KEY_CHECKS = 0;");
        $tables[]="SET FOREIGN_KEY_CHECKS = 1;";
        foreach($tables as $t){
            self::statement($t);
        } 
    }

    /**
     * Retrieves migration stages between two versions.
     *
     * @param string|null $from Starting version.
     * @param string|null $to Target version.
     * @return array Array of StageMigration objects.
     */
    protected function getStages($from=null,$to=null){
        $origins = [];
        $stages=[];
        $stagesObj=[];
        $origins[]= './source';
        $moduleList = $this->getActiveModules();
        foreach($moduleList as $module){
            $currentPath = getcwd() .'/modules/'.$module.'/migration/source';
            if(is_dir($currentPath)) {
                $origins[]=$currentPath;
            }
        }
        foreach($origins as $origin){
            $moduleStages=@scandir($origin);
            unset($moduleStages[0]);
            unset($moduleStages[1]);
            foreach($moduleStages as $moduleStage){
                if( !array_key_exists( $moduleStage, $stages ) ){
                    $stages[$moduleStage] = [];
                }
                $path = $origin . '/'. $moduleStage;
                $stages[$moduleStage][]= $path;
            }
        }
        ksort($stages);

        foreach($stages as $k=>$stage){
            if($from && $k <= $from){
                continue;
            }
            if($to && $k > $to){
                continue;
            }
            foreach($stage as $path){
                $stagesObj[]= new StageMigration($path,$this);
            }
        }
        return $stagesObj;
    }

    /**
     * Gets the list of active modules from the environment.
     *
     * @return array List of active module names.
     */
    protected function getActiveModules(){
        $moduleList = [];
        $modulesDir=@scandir( getcwd() .'/modules/');
        $activeModules = array_key_exists( "MODULES", $_ENV ) && strlen($_ENV["MODULES"]) ? explode(",", ( $_ENV["MODULES"] )) : [];
        if(count($activeModules) > 1){
            foreach($activeModules as $module){
                $finder=array_search($module, $modulesDir);
                if($finder){
                    $moduleList[]= $module;
                }
            }
        }
        return $moduleList;
    }

    /**
     * Sets the current migration version in the status table.
     *
     * @param string $version Migration version to set.
     * @return void
     */
    public function setCurrentVersion($version){
        $q="insert into SO____migration_status(id,version) values(1,?) on duplicate key update version=?";
        self::statement($q,[$version,$version]);
    }

    /**
     * Gets the current migration version from the status table.
     * Initializes the table if it does not exist.
     *
     * @param bool $init Whether to initialize the table if missing.
     * @return string|null Current migration version or null.
     * @throws \Illuminate\Database\QueryException
     */
    public function getCurrentVersion($init=true){
        $q="select version from SO____migration_status limit 1";
        try{
            $r=self::select($q);
            if(count($r)){
                return $r[0]->version;
            }else{
                return null;
            }
        }catch(\Illuminate\Database\QueryException $e){
            switch($e->getCode()){
                case "42S02":
                    $this->initialize();
                    return null;
                default:
                    throw $e;
            }
        }
    }

    /**
     * Initializes the migration status table.
     *
     * @return void
     */
    protected function initialize(){
        $q="CREATE TABLE SO____migration_status(
            id BIGINT NOT NULL auto_increment,
            version varchar(100) NOT NULL,
            lastUpdate timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        );";
        self::statement($q);
    }

    /**
     * Initializes the database connection using credentials.
     *
     * @return void
     */
    protected function initCredentials(){
        parent::__construct();
        
        $this->addConnection([
            "driver" => $this->_credentials->DB_CONNECTION,
            "host" => $this->_credentials->DB_HOST,
            "database" =>  $this->_credentials->DB_DATABASE,
            "username" =>  $this->_credentials->DB_USERNAME,
            "password" =>  $this->_credentials->DB_PASSWORD,
            "port" =>  $this->_credentials->DB_PORT,
            "charset" =>  $this->_credentials->DB_CHARSET,
            "collation" =>  $this->_credentials->DB_COLLATION
        ]);
        $this->setAsGlobal();
        $this->bootEloquent();
    }
}