<?php
namespace Paynalton\Mitladb\Migration;

use Exception;
use Illuminate\Database\Capsule\Manager;

/**
 * Class StageMigration
 *
 * Represents a migration stage, handling the application, rollback, and seeding of database changes.
 */
class StageMigration{
    /**
     * @var string $_path Path to the migration stage directory.
     */
    protected $_path="";

    /**
     * @var Manager $_db Reference to the database manager.
     */
    protected $_db;

    /**
     * @var string $name Name of the migration stage.
     */
    public $name="";

    /**
     * StageMigration constructor.
     *
     * @param string $path Path to the migration stage directory.
     * @param Manager $db Reference to the database manager.
     */
    function __construct($path,Manager &$db)
    {
        $this->_path=$path;
        $this->_db=&$db;
        $this->name=basename($path);
    }

    /**
     * Applies the migration by executing all "up" structure SQL files.
     *
     * @throws Exception If any SQL file execution fails.
     * @return void
     */
    public function apply(){
        $files=$this->getStructure();
        foreach($files as $f){
            try{
                echo "Ejecutando $f...";
                $this->_db::statement(file_get_contents($f));
                echo "OK\n";
            }catch(\Exception $e){
                echo "ERROR\n";
                throw new Exception("Error en archivo $f: ".$e->getMessage());
            }
        }
    }

    /**
     * Reverts the migration by executing all "down" structure SQL files in reverse order.
     *
     * @throws Exception If any SQL file execution fails.
     * @return void
     */
    public function undo(){
        $files=$this->getStructure("down");
        $files=array_reverse($files);
        foreach($files as $f){
            try{
                $this->_db::statement(file_get_contents($f));
            }catch(\Exception $e){
                throw new Exception("Error en archivo $f: ".$e->getMessage());
            }
        }
    }

    /**
     * Seeds the database by executing all seed SQL files.
     *
     * @throws Exception If any SQL file execution fails.
     * @return void
     */
    public function seed(){
        $files=$this->getSeeds();
        foreach($files as $f){
            try{
                echo "Seeding $f...";
                $this->_db::statement(file_get_contents($f));
                echo "OK\n";
            }catch(\Exception $e){
                echo "ERROR\n";
                throw new Exception("Error en archivo $f: ".$e->getMessage());
            }
        }
    }

    /**
     * Gets the list of structure SQL files for migration.
     *
     * @param string $orientation "up" for applying, "down" for reverting.
     * @return array List of SQL file paths.
     */
    protected function getStructure($orientation="up"){
        if(!file_exists($this->_path."/$orientation/structure")){
            return [];
        }
        $dir=scandir($this->_path."/$orientation/structure");
        array_shift($dir);
        array_shift($dir);
        foreach($dir as &$d){
            $d=$this->_path."/$orientation/structure/".$d;
        }
        return $dir;
    }

    /**
     * Gets the list of seed SQL files for migration.
     *
     * @return array List of SQL file paths.
     */
    protected function getSeeds(){
        if(!file_exists($this->_path."/up/seeder")){
            return [];
        }
        $path=$this->_path."/up/seeder";
        $dir=[];
        if(file_exists($path)){
            $dir=@scandir($path);
            array_shift($dir);
            array_shift($dir);
            foreach($dir as &$d){
                $d=$path."/".$d;
            }
        }
        return $dir;
    }
}