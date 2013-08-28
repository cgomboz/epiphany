<?php
class EpiSession_Memcached implements EpiSessionInterface
{
  private static $connected = false;
  private $key  = null;
  private $store = null;

  private $host = null;
  private $port = null;
  private $compress = null;
  private $expiry = null;
  private $options = null;

  public function __construct($params = array())
  {
    if(empty($_COOKIE[EpiSession::COOKIE]))
    {
      $cookieVal = md5(uniqid(rand(), true));
      setcookie(EpiSession::COOKIE, $cookieVal, time()+1209600, '/');
      $_COOKIE[EpiSession::COOKIE] = $cookieVal;
    }

    $this->host = !empty($params[0]) ? $params[0] : 'localhost';
    $this->port = !empty($params[1]) ? $params[1] : 11211;
    $this->compress = !empty($params[2]) ? $params[2] : 0;
    $this->expiry   = !empty($params[3]) ? $params[3] : 3600;
    $this->options  = !empty($params[4]) ? $params[4] : array();
  }

  public function end()
  {
    if(!$this->connect())
      return;

    $this->memcached->delete($this->key);
    $this->store = null;
    setcookie(EpiSession::COOKIE, null, time()-86400);
  }

  public function get($key = null)
  {
    if(!$this->connect() || empty($key) || !isset($this->store[$key]))
      return false;

    return $this->store[$key];
  }

  public function getAll()
  {
    if(!$this->connect())
      return;

    return $this->memcached->get($this->key);
  }

  public function set($key = null, $value = null)
  {
    if(!$this->connect() || empty($key))
      return false;
    
    $this->store[$key] = $value;
    $this->memcached->set($this->key, $this->store, time() + $this->expiry);
    return $value;
  }

  private function connect($params = null)
  {
    if(self::$connected)
      return true;

    if(class_exists('Memcached'))
    {
      $this->memcached = new Memcached;

      if(count($this->host) !== count($this->port))
        EpiException::raise(new EpiSessionMemcacheConnectException('Multiple servers require the same nubmber of hosts & ports'));

      $servers = array();

      // list of both host and port
      if(is_array($this->host) && is_array($this->port))
        foreach($this->host as $i => $host)
          $servers[] = array($host, $this->port[$i]);

      // all hosts with the same port
      elseif(is_array($this->host) && is_scalar($this->port))
        foreach($this->host as $i => $host)
          $servers[] = array($host, $this->port);

      // one host with multiple ports
      elseif(is_scalar($this->host) && is_array($this->port))
        foreach($this->port as $i => $port)
          $servers[] = array($this->host, $port);

      // single server
      else
        $servers[] = array($this->host, $this->port);

      foreach($this->options as $name => $value)
        $this->memcached->setOption($name, $value);

      if($this->memcached->addServers($servers))
      {
        self::$connected = true;
        $this->key = $_COOKIE[EpiSession::COOKIE];
        $this->store = $this->getAll();
        return true;
      }
      else
      {
        EpiException::raise(new EpiSessionMemcacheConnectException('Could not connect to memcache server'));
      }
    }
    EpiException::raise(new EpiSessionMemcacheClientDneException('Could not connect to memcache server'));
  }
}

class EpiSessionMemcacheConnectException extends EpiException {}
class EpiSessionMemcacheClientDneException extends EpiException {}
