<?php
// get the HTTP method, path and body of the request
$method = $_SERVER['REQUEST_METHOD'];

// decoupe l'url pour en garder les elements
// $request = explode('/', trim($_SERVER['PATH_INFO'], '/')); // OLD
$request = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$requestLength = count($request);
$reqValue = $request[1];
$reqKey = $request[2];

// Decode raw body typ aplication/json
$body = json_decode(file_get_contents('php://input'), true);

// connect to the sqlite database
try {
	$pdo = new PDO('sqlite:'.dirname(__FILE__).'/database.sqlite');
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // WARNING | EXCEPTION | SILENT
	$pdo->query("CREATE TABLE IF NOT EXISTS " . $request[0] . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, key VARCHAR(250), message VARCHAR(250) );");
} catch (Exception $e) {
	// echo "Impossible d'accéder à la base de données SQLite : " . $e->getMessage();
	// include_once("hw.php");
	echo "Hello world";
	die();
}

// compare le premier element de notre tableau de requete contre une regexp 
$table = preg_replace('/[^a-z0-9_]+/i', '', array_shift($request));

// id est le deuxième élément du tableau (+0 en fait un int)
$id = array_shift($request)+0;
$columns;
$values;

if ($body) {
	// escape the columns and values from the input object
	$columns = preg_replace('/[^a-z0-9_]+/i', '', array_keys($body));
	
	$values = array_map(function ($value) {
		if ($value===null) {
			return null;
		} else if (is_string($value)) {
			return '"' . $value . '"';
		} else {
			return $value;
		}
	},array_values($body));
	
	$set = '';
	
	for ($i=0; $i<count($columns); $i++) {
		$set .= ($i>0 ? ',' : '').$columns[$i].'=';
		$set .= ($values[$i]===null?'NULL':$values[$i]);
	}
}

// create SQL based on HTTP method
switch ($method) {
	case 'GET':
		if($requestLength == 3) {
			$sql = 'SELECT "' . $reqKey . '" FROM "' . $table . '" WHERE id=' . $id . ' LIMIT 100';
		} else if ($requestLength == 2) {
			if(is_numeric($reqValue) == true) {
				$sql = 'SELECT * FROM "' . $table . '"' . ($id?' WHERE id=' . $id : ' LIMIT 100');
			} else if(is_numeric($reqValue) == false) {
				$sql = 'SELECT "' . $reqValue . '" FROM "' . $table . '" LIMIT 100';
			}
		} else {
			$sql = 'SELECT * FROM "' . $table . '" LIMIT 100';
		}	
		break;
	case 'PUT':
		$sql = 'UPDATE "' . $table . '" SET ' . $set . ' WHERE id=' . $id;
		break;
	case 'POST':
		$sql = 'INSERT INTO "' . $table . '" (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
		break;
	case 'DELETE':
		if($requestLength == 1){
			$sql = 'DROP TABLE "' . $table . '"';
		}else{
			$sql = 'DELETE FROM "' . $table . '" WHERE id=' . $id;
		}
		break;
}

// excecute SQL statement
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll();

// die if SQL statement failed
if (!$result) {
	http_response_code(404);
	die();
} else {
	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}
