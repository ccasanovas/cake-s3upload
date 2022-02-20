<?php
use Cake\Core\Configure;

$config = [
	'AwsS3' => [
		'base_url' => 'https://s3.amazonaws.com',
		'key'      => '', //required.
		'secret'   => '', //required.
		'region'   => 'us-east-1', //required. region the bucket is it
		'bucket'   => 'test-bucket-name', //required. name of the bucket
		'prefix'   => 'path/to/test/prefix' //optional. Appends this path to all s3 object address.

		/*  Campo opcional. Por defecto false. se puede poner explicitamente
	        como true para que no se use s3 y en su lugar se almacene localmente.
	        Util para testing.
	    */
	    'local_only' => false,
    ],
];

return $config;