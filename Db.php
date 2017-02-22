<?php
#[Db.php]
/*
Edited = v1.e1.05112016
Edited = v1.e2.19112016 Edited Update Method
Author = VKS
*/
class Db{

	private static $_host="localhost";
	private static $_user="username";
	private static $_password="password";
	private static $_dbname="englishquiz";
	
	// private $_conndb= false;
	protected static $_conndb= false;
	
	protected static $_last_query= null;
	protected static $_affected_rows= 0;
	
	protected static $_insert_keys= array();
	protected static $_insert_values= array();
	protected static $_update_sets = array();

	protected static $_error=array();
	
	function __construct(){
		self::connect();
	}
	
	public function link(){
		return static::$_conndb;
	}

	protected static function connect(){
		static::$_conndb=mysqli_connect(static::$_host, static::$_user, static::$_password);
		if(!static::$_conndb) {
			static::$_error['db_connection_failed']=mysqli_error(static::$_conndb);
			die('Db_Error');
		}else {
			$select_db=mysqli_select_db(static::$_conndb,static::$_dbname);
			if(!$select_db) {
				static::$_error['db_selection_failed']=mysqli_error(static::$_conndb);
				die('Db_Error');
			}
		}
		// print_r(static::$_conndb);
		mysqli_set_charset(static::$_conndb,"utf8");
	}
	
	public function close() {
		if(!mysqli_close(static::$_conndb)){
			static::$_error['FailedToCloseConnection']="Failed To Close Connection.";
			die("Db_Error");
			// return false;
		}
	} 
	
	public function escape($value) {
		if(function_exists("mysqli_real_escape_string") && !empty($value)) {
			if(get_magic_quotes_gpc()) {
				$value=stripslashes($value);
			}
			$value=mysqli_real_escape_string(static::$_conndb,$value);
		}else {
			if(get_magic_quotes_gpc()) {
				$value=addcslashes($value);
			}
		}
		return $value;
	}
	
	protected function displayQuery($result) {
		if(!$result) {
			$output="<small>Database query failed </small>: ".mysqli_error(static::$_conndb);
			$output.="Last SQL query was: ".static::$_last_query;
			die($output);
		}else {
			static::$_affected_rows = mysqli_affected_rows(static::$_conndb);
		}
	}
	
	protected function query2db($sql) {
		static::$_last_query=$sql;
		$result=mysqli_query(static::$_conndb,$sql);
		$this->displayQuery($result);
		return $result;
	}
	
	protected function row_numbers($sql){
		$result=$this->query2db($sql);
		return mysqli_num_rows($result);
	}
	
	public function lastId() {
		return mysqli_insert_id(static::$_conndb);
	}

 // INSERT INTO `answers`(`id`, `answer`, `correct`, `attributes`, `approved`) VALUES ([value-1],[value-2],[value-3],[value-4],[value-5])

	public function insert($args=array()){
		if(!isset($args['table']) || !isset($args['values'])){
			die('incomplete values');
			return false;
		}
		/*array(
			'table'=> 'answer',
			'values'=> array('answer'=>'{$sdfsdf}'),

			)*/
		$table=$args['table'];
		$values=$this->process_insert($args['values']);
		$query="INSERT INTO {$table} {$values}";
		$result=$this->query2db($query);
		if($result){
			return true;
		}else{
			return false;
		}
	}

	protected function process_insert($data=array()){
		$values="";
		$fields="";
		$glue=", ";
		if(isset($data)){
			foreach($data as $key=> $value){
				$key=$this->escape($key);
				$value=$this->escape($value);
				$fields .="{$key}".$glue;
				$values .="'{$value}'".$glue;
			}
			$fields=rtrim($fields, $glue);
			$values=rtrim($values, $glue);
			return "({$fields}) VALUES ({$values})";
		}else{
			return false;
		}
	}

	protected function fetchAll($sql) {
		$result=$this->query2db($sql);
		$out=array();
		while($row=mysqli_fetch_assoc($result)) {
			$out[]=$row;
		}
		mysqli_free_result($result);
		return $out;
	}

	public function selectOne($args=array()){
		$out=$this->select($args);
		return array_shift($out);
	}

	public function select($args=array()){
		/*array(
			'select' => array('','') Not Associative
			'from'=>'',
			'where'=>array(''=>''), Required Associative
			'where_mode' => AND | OR,
			'limit'=> false,
			'order'=> false,
		)*/

			// print_r($args);

			if(isset($args['from'])) {
				$table=$this->escape($args['from']);
			}else{
				return false;
			}
			if(isset($args['select'])) {
				$select_fields=$args['select'];
				// $select_fields=$this->escape($args['select']);
			}else{
				$select_fields='*';
			}
			if(isset($args['where'])) {
				$where=$args['where'];
			}
			if(isset($args['order'])) {
				$order=$args['order']; 
				// $order=$this->escape($args['order']); 
			}
			if(isset($args['limit'])) {
				$limit=$this->$args['limit'];
				// $limit=$this->escape($args['limit']);
			}

			if(isset($args['where_mode'])){
				$where_mode=$args['where_mode'];
			}else{
				$where_mode="AND";
			}

	        if (is_array($select_fields)) {
	            $fields = '';
	            foreach ($select_fields as $s) {
	                $fields .= "{$s}, ";
	            }
	            $select_fields = $this->escape(rtrim($fields, ", "));
	        }

	        $query = "SELECT {$select_fields} FROM {$table} ";
	        if(!empty($where) && is_array($where)) {
	            $query .= " WHERE " . $this->process_where($where, $where_mode);
	        }
	        if (!empty($order)) {
	            $query .= " ORDER BY " . $order;
	        }
	        if (!empty($limit)) {
	            $query .= " LIMIT " . $limit;
	        }
	        // echo $query;
	        return $this->fetchAll($query);
	}

	// Currently handles single mode AND | OR

	private function process_where($values=array(), $where_mode='AND'){
		if(is_array($values)){
			$statement="";
			foreach ($values as $head => $val) {
				$head=$this->escape($head);
				$val=$this->escape($val);
				$statement.="{$head}='{$val}' {$where_mode} ";
			}
			$statement=rtrim($statement,"{$where_mode} ");
			// echo $statement;
			return $statement;
		}else{
			return false;
		}
	}

//UPDATE `users` SET `id`=[value-1],`username`=[value-2],`password`=[value-3],`firstname`=[value-4],`lastname`=[value-5],`mobile`=[value-6],`email`=[value-7],`secret`=[value-8],`authkey`=[value-9],`approved`=[value-10],`type`=[value-11],`authenticated`=[value-12],`remarks`=[value-13] WHERE 1

	public function update($args=array()){
/*		array(
			'table'=>'',
			'set'=>array(
				'filed_name'=>'data',
				'filed_name'=>'data')
			'where'=>array(''=>''), // Required Associative
			'where_mode' => 'AND | OR',
			);*/
			if(!isset($args['table'],$args['set'],$args['where'])){
				return false;
			}
			if(isset($args['where'])) {
				$where=$args['where'];
			}
			$table=$this->escape($args['table']);
			if(isset($args['where_mode'])){
				$where_mode=$this->escape($args['where_mode']);
			}else{
				$where_mode="AND";
			}
			if(isset($args['set'])){
				$set=$args['set'];
				$set=$this->process_where($set, ", ");
			}else{
				return false;
			}

			$where=$this->process_where($where, $where_mode);
			$query="UPDATE {$table} SET {$set} WHERE {$where}";
			$result = $this->query2db($query);
			return $result;
	}


	protected function encryptIt( $q ) {
	    $cryptKey  = 'a35435uhig5uyg34yg5u3y4g5u3g5u3g5uuy34g5dsfs8d09';
	    $qEncoded      = base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, md5( $cryptKey ), $q, MCRYPT_MODE_CBC, md5( md5( $cryptKey ) ) ) );
	    return( $qEncoded );
	}

	protected function decryptIt( $q ) {
	    $cryptKey  = 'a35435uhig5uyg34yg5u3y4g5u3g5u3g5uuy34g5dsfs8d09';
	    $qDecoded      = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( $cryptKey ), base64_decode( $q ), MCRYPT_MODE_CBC, md5( md5( $cryptKey ) ) ), "\0");
	    return( $qDecoded );
	}

	public function enc_key($value=''){
		return $this->encryptIt($value);
	}

	public function dec_key($value=''){
		return $this->decryptIt($value);
	}
}

