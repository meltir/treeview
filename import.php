<?php

error_reporting(0);
ini_set("DISPLAY_ERRORS",0);
require ("vendor/autoload.php");

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\ListenerInterface;


class DirTreeListener implements ListenerInterface {

    private $ob_stack = array();

    private $temp_object = array();

    private $key = null;

    private $parent = 0;

    private $db;

    private $updatePreparedStatement = null;

    private $count = 0;

    private $create_table = "CREATE TABLE IF NOT EXISTS \"listing\" (
	\"id\"	INTEGER PRIMARY KEY AUTOINCREMENT,
	\"type\"	TEXT,
	\"name\"	TEXT,
	\"mode\"	TEXT,
	\"time\"	TEXT,
	\"size\"	INTEGER,
	\"parent\"	INTEGER
);";

    public function __construct($json_file)
    {
        $path = pathinfo($json_file);
        $db_file = $path['filename'].'.db';
        if (file_exists($db_file))
        {
            echo "DB file already exists ($db_file) !\n";
            exit(1);
        }

        $this->db = new PDO('sqlite:'.$db_file, null, null);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->query($this->create_table);
        $prepared_sql = "UPDATE listing SET 'parent'=?, 'name'=?, 'type'=?, 'mode'=?, 'size'=?, 'time'=? WHERE id=?";
        $this->updatePreparedStatement = $this->db->prepare($prepared_sql);


    }

    public function whitespace(string $whitespace): void {}

    public function endDocument(): void {}

    public function insertToDb()
    {
        $t = $this->temp_object;
        $sql = "INSERT INTO listing ('parent', 'type', 'name', 'mode', 'size', 'time') VALUES ('".$t['parent']."','".@$t['type']."','".@$t['name']."','".@$t['mode']."','".@$t['size']."','".@$t['time']."')";
        $this->db->query($sql);
        $this->count++;
        if (($this->count % 1000)==0)
        {
            echo "Processed ".$this->count."\n";
        }
        return $this->db->lastInsertId();
    }

    public function updateInDb($id)
    {
        if ($id == 0) return;
        $t = $this->temp_object;

        $this->updatePreparedStatement->execute([
            @$t['parent'],
            @$t['name'],
            @$t['type'],
            @$t['mode'],
            @$t['size'],
            @$t['time'],
            $id
        ]);
    }

    public function startDocument(): void
    {
        $this->db->query('delete from listing;');
        $this->db->query('delete from sqlite_sequence;');
        $t = array();
        $t['mode'] = 0;
        $t['size'] = 0;
        $t['time'] = 0;
        $t['name'] = 'root';
        $t['parent'] = 0;
        $t['type'] = 'root';
        $t['id'] = 0;
        $this->temp_object = $t;
        $this->parent = $this->insertToDb();
        $this->ob_stack[]=$this->temp_object;
    }


    public function startObject(): void
    {
        $this->updateInDb($this->temp_object['id']);
        $this->temp_object = array();
        $this->temp_object['parent'] = $this->parent;
        $id = $this->insertToDb();
        $this->temp_object['id'] = $id;
    }

    public function endObject(): void
    {
        $this->updateInDb($this->temp_object['id']);
    }

    public function startArray(): void
    {
//        if ($this->parent==0) return;
        $this->ob_stack[]= $this->temp_object;
        $this->parent = $this->temp_object['id'];
    }

    public function endArray(): void
    {
        $this->temp_object = array_pop($this->ob_stack);
        $this->parent = $this->temp_object['id'];
    }

    public function key(string $key): void
    {
        $this->temp_object[$key] = null;
        $this->key = $key;
    }

    /**
     * @inheritDoc
     */
    public function value($value)
    {
        $this->temp_object[$this->key] = $value;
    }

}

$file = @$argv[1];

if (!$file)
{
    echo "No file to import !\n";
    echo "Generate a folder listing using 'tree -JspaguD <folder to index> -o <filename>.json'\n";
    exit(1);
}

$stream = fopen($file, 'r');
if (!$stream)
{
    echo "File '$file' not found !\n";
    exit(1);
}

$listener = new DirTreeListener($file);
try {
    $parser = new Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}

