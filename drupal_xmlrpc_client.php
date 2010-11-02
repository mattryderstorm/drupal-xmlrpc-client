<?php
/**
 * Drupal XML-RPC Client 
 *
 * A client to interface with Drupals XML-RPC services.
 *
 * PHP version 5
 *
 *
 * Simple example using 'system.listMethods'
 * <code>
 * <?php
 * $dc =& new DrupalXmlrpcClient('http://example.com/xmlrpc.php');
 * $response = $dc->{'system.listMethods'}()->getResponse();
 * print_r($response);
 * ?>
 * </code>
 *
 * 
 * More involved example using 'node.save' to create a new node
 * for a specific user. This example requires that your site have the
 * Services module, Node Service, User Service, System Service,
 * XMLRPC Server and Key Authentication all enabled.
 *
 * Create an API-KEY on your site and make sure the user has access to
 * create story content.
 * <code>
 * <?php
 * $user = 'username';
 * $pass = 'password';
 * $host = 'http://example.com/services/xmlrpc';
 * $apik = <YOUR-API-KEY>;
 * $node = array(
 *   'type' => 'story',
 *   'title' => 'A Story',
 *   'body' => 'This is a new story.'
 * );
 * $dc =& new DrupalXmlrpcClient($host, $apik);
 * $response = $dc->{'system.connect'}()->{'user.login'}($user, $pass)->{'node.save'}($node)->getResponse();
 * $dc->{'user.logout'}();
 * print_r($response);
 * ?>
 * </code>
 *
 * @copyright 2009-2010 Cheyenne Smith
 * @author Cheyenne Smith <chey.smith@gmail.com> 
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class DrupalXmlrpcClient {

/**
 * Client domain
 * 
 * @see setDomain
 * @var string
 * @access protected
 */
	protected $domain = 'localhost';

/**
 * Remote host 
 * 
 * @see __construct
 * @var string
 * @access protected
 */
	protected $host = null;

/**
 * API key required by remote host if applicable
 * 
 * @see __construct
 * @var string
 * @access protected
 */
	protected $apiKey = null;

/**
 * The Session ID provided by the remote host
 * 
 * @var string
 * @access protected
 */
	protected $sessid = null;

/**
 * Extra HTTP headers
 * 
 * @see setHeaders
 * @var array
 * @access protected
 */
	protected $headers = array();

/**
 * Container for latest response received from the remote host
 * 
 * @var mixed false if there are problems
 * @access protected
 */
	protected $response = null;

/**
 * Use the API-KEY and Session ID in the requests. This only applies if
 * using API-KEY authentication.
 * 
 * @see setPersist
 * @var boolean
 * @access protected
 */
	protected $persist = false;

/**
 * Constructor
 * 
 * @param string $host 
 * @param string $apiKey 
 * @access public
 * @return void
 */
	public function __construct($host, $apiKey = null) {
		$this->host = $host;
		$this->apiKey = $apiKey;
		if ($apiKey) {
			$this->persist = true;
		}
		$this->setDomain();
	}

/**
 * Execute XML-RPC call on remote site
 * 
 * @param string $method
 * @param mixed $params
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function call() {
		if ($this->persist && false === $this->response) {
			return $this;
		}
		if (!func_num_args()) {
			$this->response = false;
			trigger_error(sprintf('%s::%s missing arguments', __CLASS__, __FUNCTION__));
			return $this;
		}
		$args = func_get_args();
		$method = array_shift($args);
		$params = array();
		if ($this->persist && $this->sessid) {
			$params = $this->mkAuthParams($method);
		}
		foreach ($args as $arg) {
			$params[] = $arg;
		}
		$request = xmlrpc_encode_request($method, $params);
		$context = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => array('Content-Type: text/xml') + (array) $this->headers,
				'content' => $request
			)
		));
		$file = file_get_contents($this->host, false, $context);
		if (!$file) {
			$this->response = false;
			return $this;
		}
		$response = $this->response = xmlrpc_decode($file);
		if ($response && xmlrpc_is_fault((array) $response)) {
			$this->response = false;
			trigger_error(sprintf('xmlrpc: %s (%s)', $response['faultString'], $response['faultCode']));
			return $this;
		}
		if ($this->persist && is_array($response) && array_key_exists('sessid', $response)) {
			$this->sessid = $response['sessid'];
		}
		return $this;
	}

/**
 * Set the domain
 * 
 * @param string $domain 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function setDomain($domain = null) {
		if ($domain) {
			$this->domain = $domain;
		} else {
			if (!empty($_SERVER['SERVER_NAME'])) {
				$this->domain = $_SERVER['SERVER_NAME'];
			} else {
				if (function_exists('gethostname')) {
					$this->domain = gethostname();
				} elseif($h = php_uname('n')) {
					$this->domain = $h;
				}
			}
		}
		return $this;
	}

/**
 * Set additional HTTP headers
 * 
 * @param array $headers 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function setHeaders($headers = array()) {
		$this->headers = $this->headers + (array) $headers;
		return $this;
	}

/**
 * Set persist
 * 
 * @param boolean $persist 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function setPersist($persist = true) {
		$this->persist = $persist;
		return $this;
	}

/**
 * Set the API key
 * 
 * @param string $key 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function setApiKey($key) {
		$this->apiKey = $key;
		return $this;
	}

/**
 * Set the response to null (not false)
 * 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function reset() {
		$this->response = null;
		return $this;
	}

/**
 * Method overloading
 * 
 * @see call
 * @param string $name 
 * @param mixed $args 
 * @access public
 * @return DrupalXmlrpcClient
 */
	public function __call($name, $args) {
		return call_user_func_array(array($this, 'call'), array_merge(array($name), $args));
	}

/**
 * Returns last response from remote host
 * 
 * @access public
 * @return mixed
 */
	public function getResponse() {
		return $this->response;
	}

/**
 * Unique string generator
 * 
 * @param int $length 
 * @access public
 * @return string
 */
	public function mkNonce() {
		return uniqid('', true);
	}

/**
 * Creates the parameters needed for Key Authentication
 * 
 * @param string $method 
 * @access public
 * @return array
 */
	public function mkAuthParams($method) {
		$timestamp = (string) time();
		$nonce = $this->mkNonce();
		$arr = array($timestamp, $this->domain, $nonce, $method);
		$hash = hash_hmac('sha256', implode(';', $arr), $this->apiKey);
		return array(
			$hash, $this->domain, $timestamp, $nonce, $this->sessid
		);
	}
}
?>
