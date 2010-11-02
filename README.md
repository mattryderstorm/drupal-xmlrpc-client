Drupal XML-RPC Client
========================================

A client to interface with Drupals XML-RPC services. It uses PHPs XML-RPC
functions and does not rely on any Drupal code in order to make it more
portable.


### Example
Here is a simple example using 'system.listMethods'. There is another example
in the class file that uses the 'node.save' method.

<code>
	<?php
	$dc =& new DrupalXmlrpcClient('http://example.com/xmlrpc.php');
	$response = $dc->{'system.listMethods'}()->getResponse();
	print_r($response);
	?>
</code>
