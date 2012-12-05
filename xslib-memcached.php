<?php

/*! A pure PHP client to connect to a memcached (or compatible) server

	@brief Memcached/memcachedb client
	@version 0.3
*/
class xsMemcachedp
{
	private $Host; //!< Hostname or IP of the server
	private $Port; //!< Port of the server

	private $Handle; //!< Handle to a socket, as returned by fsockopen()

	/*! Connect to a memcached -server
	
		@param Host Hostname or IP of the server
		@param Port Port of the server
		@param Timeout Connection timeout [seconds]
		@return xsMemcached on success or bool false if connection failed
	*/
	public static function Connect($Host, $Port, $Timeout = 5)
	{
		$Ret = new self();
		$Ret->Host = $Host;
		$Ret->Port = $Port;

		$ErrNo = $ErrMsg = NULL;

		// Try to open connection to the server
		if(!$Ret->Handle = @fsockopen($Ret->Host, $Ret->Port, $ErrNo, $ErrMsg, $Timeout))
			return false;

		return $Ret;
	}

	/*! Set a value, unconditionally
	
		@param Key Key
		@param Value Value
		@param TTL Time-to-live of value
		@return bool Success
	*/
	public function Set($Key, $Value, $TTL = 0)
	{
		return $this->SetOp($Key, $Value, $TTL, 'set');
	}

	/*! Add a value (only if key did not exist)
	
		@param Key Value
		@param Value Value
		@param TTL Time-to-live
		@return bool Success (false if key was found)
	*/
	public function Add($Key, $Value, $TTL = 0)
	{
		return $this->SetOp($Key, $Value, $TTL, 'add');
	}

	/*! Append to a value (only if key exists)
	
		@param Key Value
		@param Value Value
		@param TTL Time-to-live
		@return bool Success (false if key was not found)
	*/
	public function Append($Key, $Value, $TTL = 0)
	{
		return $this->SetOp($Key, $Value, $TTL, 'append');
	}

	/*! Prepend to a value (only if key exists)
	
		@param Key Value
		@param Value Value
		@param TTL Time-to-live
		@return bool Success (false if key was not found)
	*/
	public function Prepend($Key, $Value, $TTL = 0)
	{
		return $this->SetOp($Key, $Value, $TTL, 'prepend');
	}

	/*! Replace a value (= write only if key exists)
	
		@param Key Value
		@param Value Value
		@param TTL Time-to-live
		@return bool Success (false if key was not found)
	*/
	public function Replace($Key, $Value, $TTL = 0)
	{
		return $this->SetOp($Key, $Value, $TTL, 'replace');
	}

	/*! Get a value
	
		@param Key
		@return bool false if not found, string if found
	*/
	public function Get($Key)
	{
		$this->WriteLine('get ' . $Key);
		
		$Ret = '';

		$Header = $this->ReadLine();

		// Header not found => value not found
		if($Header == 'END')
			return false;

		while(($Line = $this->ReadLine()) != 'END')
			$Ret .= $Line;

		if($Ret == '')
			return false;

		$Header = explode(' ', $Header);
		
		if($Header[0] != 'VALUE' || $Header[1] != $Key)
			throw new Exception('unexcpected response format');

		$Meta = $Header[2];
		
		$Len = $Header[3];
		
		return $Ret;
	}

	/*! Delete a value
	
		@param Key Key
		@return bool Success (false if key did not exist)
	*/
	public function Delete($Key)
	{
		return $this->WriteLine('delete ' . $Key, true) != 'NOT_FOUND';
	}

	/*! Increment a counter, but only if it exists
	
		@param Key Key
		@param Amount Amount to decrement
		@return bool Success
	*/
	public function Incr($Key, $Amount = 1)
	{
		return ($Ret = $this->WriteLine('incr ' . $Key . ' ' . $Amount, true)) != 'NOT_FOUND' ?
			$Ret :
			false
		;
	}

	/*! Decrement a counter, but only if it exists (does not go below 0)
	
		@param Key Key
		@param Amount Amount to decrement
		@return bool Success
	*/
	public function Decr($Key, $Amount = 1)
	{
		return ($Ret = $this->WriteLine('decr ' . $Key . ' ' . $Amount, true)) != 'NOT_FOUND' ?
			$Ret :
			false
		;
	}	

	/*! Return statistics
	
		@param Key Key to return from statistics
		@return array of statistics. Ex: [pid: 2131, uptime: ...] or or false if Key given & not found
	*/
	public function Stats($Key = NULL)
	{
		$Ret = array();
		
		$this->WriteLine('stats');

		while(($Line = $this->ReadLine()) != 'END')
		{
			$Line = explode(' ', $Line);
			
			if($Line[0] != 'STAT')
				throw new Exception('unexcpected response format');

			$Ret[$Line[1]] = $Line[2];
		}
		
		if($Key)
			return isset($Ret[$Key]) ? $Ret[$Key] : false;

		return $Ret;
	}

	/*! Invalidate all existing items
	
		@param Expiration Expiration before flushing
		@return bool Success
	*/
	public function Flush ($Expiration = 0)
	{
		return $this->WriteLine('flush_all ' . $Expiration);
	}
	
	/*! Close the connection
	
		@return void
	*/
	public function Quit()
	{
		$this->WriteLine('quit');
	}

	/*! Helper to do all set operations (set/add/replace/append/prepend)
	
		@param Key Value
		@param Value Value
		@param TTL Time-to-live
		@param Op Operation name (set/add/...)
		@return bool Success
	*/
	private function SetOp($Key, $Value, $TTL, $Op)
	{
		$this->WriteLine($Op . ' ' . $Key . ' 0 ' . $TTL . ' ' . strlen($Value));
		
		$this->WriteLine($Value);
	
		return $this->ReadLine() == 'STORED';
	}

	/*! Helper function to write a line into the socket
	
		@param Command Command to write, without the line ending
		@param Response Read response too? If set to true, returns ReadLine()
		@sa ReadLine()
		@return bool Success or string from ReadLine() (see Response parameter)
	*/
	private function WriteLine($Command, $Response = false)
	{
		fwrite($this->Handle, $Command . "\r\n");
		
		if($Response)
			return $this->ReadLine();

		return true;
	}

	/*! Helper function to read a line from the socket
	
		@return string line of response (without the line ending)
	*/
	private function ReadLine()
	{
		return rtrim(fgets($this->Handle), "\r\n");
	}

	//! Do not allow $m = new xsMemcached(); syntax
	private function __construct()
	{
		// stub
	}
}